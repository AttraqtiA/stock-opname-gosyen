<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\OpnameSession;
use App\Models\OpnameSessionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockOpnameController extends Controller
{
    public function index(): JsonResponse
    {
        $companyId = request()->integer('company_id') ?: Company::query()->where('status', 'approved')->orderBy('name')->value('id');
        if ($companyId) {
            Company::query()->where('status', 'approved')->findOrFail($companyId);
        }

        return response()->json($this->payload($companyId));
    }

    public function storeItem(Request $request): JsonResponse
    {
        $request->merge([
            'name' => Str::of($request->input('name', ''))->squish()->toString(),
            'type' => Str::of($request->input('type', ''))->squish()->toString(),
            'unit' => Str::of($request->input('unit', ''))->squish()->toString(),
        ]);

        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', 'approved')],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where('company_id', $request->integer('company_id')),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stock_items', 'name')
                    ->where('company_id', $request->integer('company_id'))
                    ->whereNull('deleted_at'),
            ],
            'type' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:32'],
            'system_stock' => ['required', 'integer', 'min:0'],
            'actual_stock' => ['required', 'integer', 'min:0'],
        ], [
            'name.unique' => 'Nama stok ini sudah ada di company aktif.',
        ]);

        DB::transaction(function () use ($data, $request): void {
            $company = Company::lockForUpdate()->findOrFail($data['company_id']);
            $type = $this->displayTypeForCompany($company, $data['type']);
            $item = StockItem::create([
                'code' => $this->nextCode($company),
                'company_id' => $company->id,
                'warehouse_id' => $data['warehouse_id'],
                'name' => $data['name'],
                'type' => $type,
                'normalized_type' => $this->normalizeType($data['type']),
                'unit' => $data['unit'],
                'system_stock' => $data['system_stock'],
                'actual_stock' => $data['actual_stock'],
            ]);

            // Add item to active session if exists
            $activeSession = OpnameSession::where('company_id', $company->id)
                ->where('status', 'active')
                ->first();

            if ($activeSession) {
                OpnameSessionItem::create([
                    'opname_session_id' => $activeSession->id,
                    'stock_item_id' => $item->id,
                    'system_stock' => $data['system_stock'],
                    'actual_stock' => $data['actual_stock'],
                ]);
            }

            $this->recordMovement($item, 'create', $data['actual_stock'], $this->movementMeta($request, $company), 'Barang baru', $activeSession?->id);
        });

        return response()->json($this->payload((int) $data['company_id']), 201);
    }

    public function storeMovement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', 'approved')],
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'kind' => ['required', Rule::in(['in', 'out', 'count', 'sync'])],
            'quantity' => ['required', 'integer', Rule::when($request->input('kind') === 'sync', ['min:0'], ['min:1'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $activeSession = OpnameSession::where('company_id', $data['company_id'])
            ->where('status', 'active')
            ->first();

        $pendingSession = OpnameSession::where('company_id', $data['company_id'])
            ->where('status', 'pending_approval')
            ->first();

        if ($pendingSession) {
            abort(422, 'Sesi opname sedang menunggu persetujuan. Tidak dapat melakukan pencatatan.');
        }

        if (in_array($data['kind'], ['count', 'sync']) && !$activeSession) {
            abort(422, 'Tidak ada sesi opname aktif. Silakan buka sesi baru.');
        }

        DB::transaction(function () use ($data, $request, $activeSession): void {
            $company = Company::query()->findOrFail($data['company_id']);
            $item = StockItem::query()
                ->where('company_id', $data['company_id'])
                ->lockForUpdate()
                ->findOrFail($data['stock_item_id']);
            $quantity = (int) $data['quantity'];

            if ($activeSession) {
                $sessionItem = OpnameSessionItem::firstOrCreate([
                    'opname_session_id' => $activeSession->id,
                    'stock_item_id' => $item->id,
                ], [
                    'system_stock' => $item->system_stock,
                    'actual_stock' => 0,
                ]);

                if ($data['kind'] === 'in') {
                    $sessionItem->actual_stock += $quantity;
                }
                if ($data['kind'] === 'out') {
                    $sessionItem->actual_stock = max(0, $sessionItem->actual_stock - $quantity);
                }
                if ($data['kind'] === 'count') {
                    $sessionItem->actual_stock = $quantity;
                }
                if ($data['kind'] === 'sync') {
                    $diff = abs($sessionItem->actual_stock - $sessionItem->system_stock);
                    if ($diff >= 10 && !$request->user()->isAdmin()) {
                        abort(403, 'Persetujuan supervisor diperlukan untuk sinkronisasi selisih besar (>= 10 unit).');
                    }
                    $quantity = $diff;
                    $sessionItem->actual_stock = $sessionItem->system_stock;
                }
                $sessionItem->save();

                $item->actual_stock = $sessionItem->actual_stock;
            } else {
                if ($data['kind'] === 'in') {
                    $item->actual_stock += $quantity;
                }
                if ($data['kind'] === 'out') {
                    $item->actual_stock = max(0, $item->actual_stock - $quantity);
                }
            }

            $this->recordMovement($item, $data['kind'], $quantity, $this->movementMeta($request, $company), $data['note'] ?? null, $activeSession?->id);
            $item->save();
        });

        return response()->json($this->payload((int) $data['company_id']));
    }

    public function updateItem(Request $request, StockItem $stockItem): JsonResponse
    {
        $merge = [];
        if ($request->has('type')) {
            $merge['type'] = Str::of($request->input('type', ''))->squish()->toString();
        }
        if ($request->has('unit')) {
            $merge['unit'] = Str::of($request->input('unit', ''))->squish()->toString();
        }
        if ($merge !== []) {
            $request->merge($merge);
        }

        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', 'approved')],
            'system_stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'type' => ['sometimes', 'required', 'string', 'max:255'],
            'unit' => ['sometimes', 'required', 'string', 'max:32'],
        ]);

        DB::transaction(function () use ($data, $request, $stockItem): void {
            $company = Company::query()->findOrFail($data['company_id']);
            $item = StockItem::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->findOrFail($stockItem->id);

            $changes = [];

            if (array_key_exists('system_stock', $data)) {
                abort_unless($request->user()->isAdmin(), 403, 'Hanya admin yang dapat mengedit stok sistem.');
                $item->system_stock = (int) $data['system_stock'];
                $changes[] = 'stok sistem';
            }

            if (array_key_exists('type', $data)) {
                $item->type = $this->displayTypeForCompany($company, $data['type']);
                $item->normalized_type = $this->normalizeType($data['type']);
                $changes[] = 'tipe';
            }

            if (array_key_exists('unit', $data)) {
                $item->unit = $data['unit'];
                $changes[] = 'satuan';
            }

            abort_if($changes === [], 422, 'Tidak ada perubahan produk yang dikirim.');

            $this->recordMovement($item, 'update', 0, $this->movementMeta($request, $company), 'Edit '.implode(' & ', $changes));
            $item->save();
        });

        return response()->json($this->payload((int) $data['company_id']));
    }

    public function destroyItem(Request $request, StockItem $stockItem): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', 'approved')],
        ]);

        abort_unless($request->user()->isAdmin(), 403, 'Hanya admin yang dapat menghapus produk.');

        DB::transaction(function () use ($data, $request, $stockItem): void {
            $company = Company::query()->findOrFail($data['company_id']);
            $item = StockItem::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->findOrFail($stockItem->id);

            $this->recordMovement($item, 'delete', 0, $this->movementMeta($request, $company), 'Produk dihapus dari stok aktif');
            $item->delete();
        });

        return response()->json($this->payload((int) $data['company_id']));
    }

    public function export(Request $request)
    {
        $company = Company::query()->where('status', 'approved')->findOrFail($request->query('company_id'));
        $exportedAt = now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $location = $company->location ?: 'Gudang Utama';
        $officer = $request->user()->name;
        $filename = 'stock-opname-'.$company->code_prefix.'-'.now()->format('Y-m-d').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($company, $exportedAt, $location, $officer): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Company', $company->name]);
            fputcsv($handle, ['Tanggal Export', $exportedAt]);
            fputcsv($handle, ['Lokasi', $location]);
            fputcsv($handle, ['Petugas', $officer]);
            fputcsv($handle, []);
            fputcsv($handle, ['No.', 'Kode Barang', 'Nama Barang', 'Gudang', 'Stock Program', 'Opname', 'Selisih', 'Ket']);

            $rowNumber = 1;

            StockItem::query()
                ->with([
                    'warehouse',
                    'movements' => fn ($query) => $query->with('user:id,name,email')->whereIn('kind', ['count', 'in', 'out'])->oldest()
                ])
                ->where('company_id', $company->id)
                ->orderBy('code')
                ->each(function (StockItem $item) use ($handle, &$rowNumber): void {
                    $diff = $item->actual_stock - $item->system_stock;
                    fputcsv($handle, [
                        $rowNumber++,
                        $item->code,
                        $item->name,
                        $item->warehouse?->name ?: '-',
                        $this->formatExportNumber($item->system_stock),
                        $this->formatExportNumber($item->actual_stock),
                        $this->formatExportNumber($diff),
                        $this->exportNote($item),
                    ]);
                });

            fclose($handle);
        }, $filename, $headers);
    }

    public function storeCompany(Request $request): JsonResponse
    {
        $request->merge([
            'name' => Str::of($request->input('name', ''))->squish()->toString(),
            'location' => Str::of($request->input('location', ''))->squish()->toString(),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:companies,name'],
            'location' => ['nullable', 'string', 'max:255'],
            'pic_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [
            'name.required' => 'Nama company wajib diisi.',
            'name.unique' => 'Company dengan nama ini sudah ada.',
        ]);

        $company = DB::transaction(function () use ($data, $request): Company {
            Company::query()->lockForUpdate()->get(['id']);

            return Company::create([
                'name' => $data['name'],
                'location' => $data['location'] ?: null,
                'pic_user_id' => $data['pic_user_id'] ?? null,
                'code_prefix' => $this->nextCompanyPrefix($data['name']),
                'next_stock_number' => 1,
                'status' => $request->user()->isAdmin() ? 'approved' : 'pending',
                'requested_by_user_id' => $request->user()->id,
                'approved_by_user_id' => $request->user()->isAdmin() ? $request->user()->id : null,
                'approved_at' => $request->user()->isAdmin() ? now() : null,
            ]);
        });

        if ($company->status === 'pending') {
            return response()->json([
                'requestAccepted' => true,
                'message' => 'Request company dikirim. Admin perlu approve sebelum client aktif.',
                'payload' => $this->payload(),
            ], 202);
        }

        return response()->json($this->payload($company->id));
    }

    public function history(Request $request): View
    {
        $companyId = $request->integer('company_id') ?: Company::query()->where('status', 'approved')->orderBy('name')->value('id');
        if ($companyId) {
            Company::query()->where('status', 'approved')->findOrFail($companyId);
        }
        $from = $request->query('from');
        $to = $request->query('to');

        $movements = StockMovement::query()
            ->select('stock_movements.*')
            ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
            ->with(['stockItem' => fn ($query) => $query->withTrashed(), 'user:id,name,email'])
            ->where('stock_items.company_id', $companyId)
            ->when($from, fn ($query) => $query->whereDate('stock_movements.created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('stock_movements.created_at', '<=', $to))
            ->latest('stock_movements.created_at')
            ->paginate(50)
            ->withQueryString();

        return view('stock-opname.history', [
            'companies' => Company::query()->where('status', 'approved')->orderBy('name')->get(['id', 'name', 'code_prefix']),
            'currentCompanyId' => $companyId,
            'movements' => $movements,
            'from' => $from,
            'to' => $to,
        ]);
    }

    private function payload(?int $companyId = null): array
    {
        $companyId = $companyId ?: Company::query()->where('status', 'approved')->orderBy('name')->value('id');

        $activeSession = OpnameSession::where('company_id', $companyId)
            ->where('status', 'active')
            ->with('creator:id,name')
            ->first();

        $pendingSession = OpnameSession::where('company_id', $companyId)
            ->where('status', 'pending_approval')
            ->with('creator:id,name')
            ->first();

        $displaySession = $activeSession ?: $pendingSession;
        $sessionItems = [];
        $sessionSystemStocks = [];

        if ($displaySession) {
            $sessionItems = DB::table('opname_session_items')
                ->where('opname_session_id', $displaySession->id)
                ->pluck('actual_stock', 'stock_item_id')
                ->all();
            $sessionSystemStocks = DB::table('opname_session_items')
                ->where('opname_session_id', $displaySession->id)
                ->pluck('system_stock', 'stock_item_id')
                ->all();
        }

        $items = StockItem::query()
            ->select(['id', 'code', 'company_id', 'warehouse_id', 'name', 'type', 'normalized_type', 'unit', 'system_stock', 'actual_stock', 'updated_at'])
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->orderBy('name')
            ->get()
            ->map(fn (StockItem $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'companyId' => $item->company_id,
                'warehouseId' => $item->warehouse_id,
                'name' => $item->name,
                'type' => $item->type,
                'normalizedType' => $item->normalized_type,
                'unit' => $item->unit,
                'systemStock' => $displaySession ? ($sessionSystemStocks[$item->id] ?? 0) : $item->system_stock,
                'actualStock' => $displaySession ? ($sessionItems[$item->id] ?? 0) : $item->actual_stock,
                'updatedAt' => $item->updated_at?->toISOString(),
            ]);

        $movements = StockMovement::query()
            ->select('stock_movements.*')
            ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
            ->with(['stockItem' => fn ($query) => $query->withTrashed(), 'user:id,name,email'])
            ->where('stock_items.company_id', $companyId)
            ->latest('stock_movements.created_at')
            ->limit(10)
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'id' => $movement->id,
                'productId' => $movement->stock_item_id,
                'productName' => $movement->stockItem?->name,
                'unit' => $movement->stockItem?->unit,
                'kind' => $movement->kind,
                'qty' => $movement->quantity,
                'location' => $movement->location,
                'officer' => $movement->officer,
                'actorName' => $movement->user?->name ?: $movement->officer,
                'accountName' => $movement->user?->name,
                'accountEmail' => $movement->user?->email,
                'note' => $movement->note,
                'at' => $movement->created_at?->toISOString(),
            ]);

        $warehouses = \App\Models\Warehouse::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'company_id', 'name', 'location'])
            ->map(fn (\App\Models\Warehouse $w): array => [
                'id' => $w->id,
                'companyId' => $w->company_id,
                'name' => $w->name,
                'location' => $w->location,
            ]);

        return [
            'companies' => Company::query()
                ->where('status', 'approved')
                ->orderBy('name')
                ->get(['id', 'name', 'location', 'pic_user_id', 'code_prefix']),
            'currentCompanyId' => $companyId,
            'products' => $items,
            'warehouses' => $warehouses,
            'activities' => $movements,
            'activeSession' => $activeSession ? [
                'id' => $activeSession->id,
                'name' => $activeSession->name,
                'status' => $activeSession->status,
                'creatorName' => $activeSession->creator?->name ?: 'System',
                'createdAt' => $activeSession->created_at?->toISOString(),
            ] : null,
            'pendingSession' => $pendingSession ? [
                'id' => $pendingSession->id,
                'name' => $pendingSession->name,
                'status' => $pendingSession->status,
                'creatorName' => $pendingSession->creator?->name ?: 'System',
                'createdAt' => $pendingSession->created_at?->toISOString(),
            ] : null,
            'pastSessions' => OpnameSession::where('company_id', $companyId)
                ->where('status', 'completed')
                ->with('completer:id,name')
                ->latest('completed_at')
                ->limit(5)
                ->get()
                ->map(fn ($s): array => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'completedAt' => $s->completed_at?->toISOString(),
                    'completerName' => $s->completer?->name ?: 'System',
                ]),
        ];
    }

    private function recordMovement(StockItem $item, string $kind, int $quantity, array $data, ?string $note, ?int $sessionId = null): void
    {
        StockMovement::create([
            'stock_item_id' => $item->id,
            'user_id' => $data['user_id'] ?? null,
            'opname_session_id' => $sessionId,
            'kind' => $kind,
            'quantity' => $quantity,
            'system_stock_before' => $item->getOriginal('system_stock') ?? $item->system_stock,
            'actual_stock_before' => $item->getOriginal('actual_stock') ?? $item->actual_stock,
            'system_stock_after' => $item->system_stock,
            'actual_stock_after' => $item->actual_stock,
            'location' => $data['location'] ?? null,
            'officer' => $data['officer'] ?? null,
            'note' => $note,
        ]);
    }

    private function movementMeta(Request $request, Company $company): array
    {
        return [
            'location' => $company->location,
            'officer' => $request->user()->name,
            'user_id' => $request->user()->id,
        ];
    }

    private function nextCode(Company $company): string
    {
        do {
            $code = sprintf('%s-%04d', $company->code_prefix, $company->next_stock_number);
            $company->next_stock_number++;
        } while (StockItem::query()->where('code', $code)->exists());

        $company->save();

        return $code;
    }

    private function nextCompanyPrefix(string $name): string
    {
        $words = Str::of($name)->replaceMatches('/[^A-Za-z0-9\s]/', ' ')->squish()->explode(' ');
        $base = $words->map(fn (string $word): string => Str::substr($word, 0, 1))->implode('');
        $base = Str::of($base ?: $name)->upper()->replaceMatches('/[^A-Z0-9]/', '')->substr(0, 4)->padRight(3, 'X')->toString();
        $prefix = $base;
        $suffix = 2;

        while (Company::query()->where('code_prefix', $prefix)->exists()) {
            $prefix = Str::substr($base, 0, 3).$suffix;
            $suffix++;
        }

        return $prefix;
    }

    private function displayTypeForCompany(Company $company, string $type): string
    {
        $normalized = $this->normalizeType($type);

        return StockItem::query()
            ->where('company_id', $company->id)
            ->where('normalized_type', $normalized)
            ->value('type') ?? Str::of($type)->squish()->toString();
    }

    private function normalizeType(string $type): string
    {
        return Str::of($type)->lower()->replaceMatches('/\s+/', '')->toString();
    }

    private function statusLabel(int $diff): string
    {
        return match (true) {
            $diff > 0 => 'Lebih',
            $diff < 0 => 'Kurang',
            default => 'Sesuai',
        };
    }

    private function exportNote(StockItem $item): string
    {
        $entries = $item->movements
            ->where('kind', 'count')
            ->map(fn (StockMovement $movement): string => sprintf(
                '%s: %s %s oleh %s%s',
                $movement->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                $this->formatExportNumber($movement->quantity),
                $item->unit,
                $movement->user?->name ?: ($movement->officer ?: '-'),
                $movement->location ? ' @ '.$movement->location : ''
            ));

        if ($entries->isEmpty()) {
            return $this->statusLabel($item->actual_stock - $item->system_stock);
        }

        return $entries->count().' input opname: '.$entries->implode('; ');
    }

    private function formatExportNumber(int $value): string
    {
        $formatted = number_format(abs($value), 2, ',', '.');

        return $value < 0 ? "({$formatted})" : $formatted;
    }

    public function storeWarehouse(Request $request): JsonResponse
    {
        $request->merge([
            'name' => Str::of($request->input('name', ''))->squish()->toString(),
            'location' => Str::of($request->input('location', ''))->squish()->toString(),
        ]);

        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', 'approved')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('warehouses', 'name')
                    ->where('company_id', $request->integer('company_id')),
            ],
            'location' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'Nama gudang ini sudah ada di company aktif.',
        ]);

        \App\Models\Warehouse::create([
            'company_id' => $data['company_id'],
            'name' => $data['name'],
            'location' => $data['location'] ?: null,
        ]);

        return response()->json($this->payload((int) $data['company_id']), 201);
    }

    public function updateWarehouse(Request $request, \App\Models\Warehouse $warehouse): JsonResponse
    {
        $request->merge([
            'name' => Str::of($request->input('name', ''))->squish()->toString(),
            'location' => Str::of($request->input('location', ''))->squish()->toString(),
        ]);

        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', 'approved')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('warehouses', 'name')
                    ->where('company_id', $request->integer('company_id'))
                    ->ignore($warehouse->id),
            ],
            'location' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'Nama gudang ini sudah ada di company aktif.',
        ]);

        abort_unless($warehouse->company_id === (int) $data['company_id'], 403, 'Akses tidak sah.');

        $warehouse->update([
            'name' => $data['name'],
            'location' => $data['location'] ?: null,
        ]);

        return response()->json($this->payload((int) $data['company_id']));
    }

    public function destroyWarehouse(Request $request, \App\Models\Warehouse $warehouse): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', 'approved')],
        ]);

        abort_unless($warehouse->company_id === (int) $data['company_id'], 403, 'Akses tidak sah.');

        $count = \App\Models\Warehouse::query()->where('company_id', $data['company_id'])->count();
        abort_if($count <= 1, 422, 'Gudang tidak dapat dihapus. Client minimal harus memiliki 1 gudang.');

        $hasItems = StockItem::query()->where('warehouse_id', $warehouse->id)->exists();
        abort_if($hasItems, 422, 'Gudang tidak dapat dihapus karena masih berisi barang. Pindahkan barang terlebih dahulu.');

        $warehouse->delete();

        return response()->json($this->payload((int) $data['company_id']));
    }

    public function storeSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', 'approved')],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $active = OpnameSession::where('company_id', $data['company_id'])
            ->where('status', 'active')
            ->first();

        if ($active) {
            abort(422, 'Sudah ada sesi opname aktif untuk company ini.');
        }

        $pending = OpnameSession::where('company_id', $data['company_id'])
            ->where('status', 'pending_approval')
            ->first();

        if ($pending) {
            abort(422, 'Terdapat sesi opname yang sedang menunggu persetujuan.');
        }

        DB::transaction(function () use ($data, $request): void {
            $session = OpnameSession::create([
                'company_id' => $data['company_id'],
                'name' => $data['name'],
                'status' => 'active',
                'created_by_user_id' => $request->user()->id,
            ]);

            $items = StockItem::where('company_id', $data['company_id'])->get();
            foreach ($items as $item) {
                OpnameSessionItem::create([
                    'opname_session_id' => $session->id,
                    'stock_item_id' => $item->id,
                    'system_stock' => $item->system_stock,
                    'actual_stock' => 0, // Reset actual stock to 0 for a new count session
                ]);

                // Reset global actual stock to 0 during active opname
                $item->actual_stock = 0;
                $item->save();
            }
        });

        return response()->json($this->payload((int) $data['company_id']), 201);
    }

    public function finalizeSession(Request $request, OpnameSession $session): JsonResponse
    {
        abort_unless($session->status === 'active', 422, 'Sesi tidak aktif.');

        $items = OpnameSessionItem::where('opname_session_id', $session->id)->get();
        $hasLargeDiscrepancy = false;

        foreach ($items as $item) {
            if (abs($item->actual_stock - $item->system_stock) >= 10) {
                $hasLargeDiscrepancy = true;
                break;
            }
        }

        if ($hasLargeDiscrepancy && !$request->user()->isAdmin()) {
            $session->update([
                'status' => 'pending_approval',
            ]);
            return response()->json([
                'pending' => true,
                'message' => 'Sesi opname menunggu persetujuan admin karena terdapat selisih >= 10 unit.',
                'payload' => $this->payload((int) $session->company_id),
            ]);
        }

        DB::transaction(function () use ($session, $items, $request): void {
            foreach ($items as $sessionItem) {
                $stockItem = StockItem::find($sessionItem->stock_item_id);
                if ($stockItem) {
                    $diff = abs($sessionItem->actual_stock - $sessionItem->system_stock);
                    $stockItem->system_stock = $sessionItem->actual_stock;
                    $stockItem->actual_stock = $sessionItem->actual_stock;
                    $stockItem->save();

                    $this->recordMovement($stockItem, 'sync', $diff, [
                        'user_id' => $request->user()->id,
                        'location' => $session->company?->location,
                        'officer' => $request->user()->name,
                    ], 'Finalisasi sesi opname: ' . $session->name, $session->id);
                }
            }

            $session->update([
                'status' => 'completed',
                'completed_by_user_id' => $request->user()->id,
                'completed_at' => now(),
            ]);
        });

        return response()->json($this->payload((int) $session->company_id));
    }

    public function approveSession(Request $request, OpnameSession $session): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya admin yang dapat menyetujui sesi opname.');
        abort_unless($session->status === 'pending_approval', 422, 'Sesi tidak sedang menunggu persetujuan.');

        $items = OpnameSessionItem::where('opname_session_id', $session->id)->get();

        DB::transaction(function () use ($session, $items, $request): void {
            foreach ($items as $sessionItem) {
                $stockItem = StockItem::find($sessionItem->stock_item_id);
                if ($stockItem) {
                    $diff = abs($sessionItem->actual_stock - $sessionItem->system_stock);
                    $stockItem->system_stock = $sessionItem->actual_stock;
                    $stockItem->actual_stock = $sessionItem->actual_stock;
                    $stockItem->save();

                    $this->recordMovement($stockItem, 'sync', $diff, [
                        'user_id' => $request->user()->id,
                        'location' => $session->company?->location,
                        'officer' => $request->user()->name,
                    ], 'Disetujui Admin: finalisasi sesi opname: ' . $session->name, $session->id);
                }
            }

            $session->update([
                'status' => 'completed',
                'completed_by_user_id' => $request->user()->id,
                'completed_at' => now(),
            ]);
        });

        return response()->json($this->payload((int) $session->company_id));
    }

    public function rejectSession(Request $request, OpnameSession $session): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya admin yang dapat menolak sesi opname.');
        abort_unless($session->status === 'pending_approval', 422, 'Sesi tidak sedang menunggu persetujuan.');

        $session->update([
            'status' => 'active',
        ]);

        return response()->json($this->payload((int) $session->company_id));
    }
}
