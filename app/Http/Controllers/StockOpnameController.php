<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\StockItem;
use App\Models\StockMovement;
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
        return response()->json($this->payload(request()->integer('company_id') ?: null));
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stock_items', 'name')->where('company_id', $request->integer('company_id')),
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
                'name' => $data['name'],
                'type' => $type,
                'normalized_type' => $this->normalizeType($data['type']),
                'unit' => $data['unit'],
                'system_stock' => $data['system_stock'],
                'actual_stock' => $data['actual_stock'],
            ]);

            $this->recordMovement($item, 'create', $data['actual_stock'], $this->movementMeta($request, $company), 'Barang baru');
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

        DB::transaction(function () use ($data, $request): void {
            $company = Company::query()->findOrFail($data['company_id']);
            $item = StockItem::query()
                ->where('company_id', $data['company_id'])
                ->lockForUpdate()
                ->findOrFail($data['stock_item_id']);
            $quantity = (int) $data['quantity'];

            if ($data['kind'] === 'in') {
                $item->actual_stock += $quantity;
            }

            if ($data['kind'] === 'out') {
                $item->actual_stock = max(0, $item->actual_stock - $quantity);
            }

            if ($data['kind'] === 'count') {
                $item->actual_stock += $quantity;
            }

            if ($data['kind'] === 'sync') {
                $quantity = abs($item->actual_stock - $item->system_stock);
                $item->actual_stock = $item->system_stock;
            }

            $this->recordMovement($item, $data['kind'], $quantity, $this->movementMeta($request, $company), $data['note'] ?? null);
            $item->save();
        });

        return response()->json($this->payload((int) $data['company_id']));
    }

    public function updateItem(Request $request, StockItem $stockItem): JsonResponse
    {
        $request->merge([
            'type' => $request->has('type') ? Str::of($request->input('type', ''))->squish()->toString() : null,
            'unit' => $request->has('unit') ? Str::of($request->input('unit', ''))->squish()->toString() : null,
        ]);

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
            fputcsv($handle, ['No.', 'Kode Barang', 'Nama Barang', 'Stock Program', 'Opname', 'Selisih', 'Ket']);

            $rowNumber = 1;

            StockItem::query()
                ->with(['movements' => fn ($query) => $query->with('user:id,name,email')->whereIn('kind', ['count', 'in', 'out'])->oldest()])
                ->where('company_id', $company->id)
                ->orderBy('code')
                ->each(function (StockItem $item) use ($handle, &$rowNumber): void {
                    $diff = $item->actual_stock - $item->system_stock;
                    fputcsv($handle, [
                        $rowNumber++,
                        $item->code,
                        $item->name,
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
        $from = $request->query('from');
        $to = $request->query('to');

        $movements = StockMovement::query()
            ->select('stock_movements.*')
            ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
            ->with(['stockItem:id,code,name,unit', 'user:id,name,email'])
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

        $items = StockItem::query()
            ->select(['id', 'code', 'company_id', 'name', 'type', 'normalized_type', 'unit', 'system_stock', 'actual_stock', 'updated_at'])
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->orderBy('name')
            ->get()
            ->map(fn (StockItem $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'companyId' => $item->company_id,
                'name' => $item->name,
                'type' => $item->type,
                'normalizedType' => $item->normalized_type,
                'unit' => $item->unit,
                'systemStock' => $item->system_stock,
                'actualStock' => $item->actual_stock,
                'updatedAt' => $item->updated_at?->toISOString(),
            ]);

        $movements = StockMovement::query()
            ->select('stock_movements.*')
            ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
            ->with(['stockItem:id,name,unit', 'user:id,name,email'])
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

        return [
            'companies' => Company::query()
                ->where('status', 'approved')
                ->orderBy('name')
                ->get(['id', 'name', 'location', 'pic_user_id', 'code_prefix']),
            'currentCompanyId' => $companyId,
            'products' => $items,
            'activities' => $movements,
        ];
    }

    private function recordMovement(StockItem $item, string $kind, int $quantity, array $data, ?string $note): void
    {
        StockMovement::create([
            'stock_item_id' => $item->id,
            'user_id' => $data['user_id'] ?? null,
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
}
