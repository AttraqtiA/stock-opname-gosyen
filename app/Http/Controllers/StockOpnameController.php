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

class StockOpnameController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json($this->payload(request()->integer('company_id') ?: null));
    }

    public function storeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:32'],
            'system_stock' => ['required', 'integer', 'min:0'],
            'actual_stock' => ['required', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'officer' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data): void {
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

            $this->recordMovement($item, 'create', $data['actual_stock'], $data, 'Barang baru');
        });

        return response()->json($this->payload((int) $data['company_id']), 201);
    }

    public function storeMovement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'kind' => ['required', Rule::in(['in', 'out', 'count', 'sync'])],
            'quantity' => ['required', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'officer' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data): void {
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
                $item->actual_stock = $quantity;
            }

            if ($data['kind'] === 'sync') {
                $quantity = abs($item->actual_stock - $item->system_stock);
                $item->actual_stock = $item->system_stock;
            }

            $this->recordMovement($item, $data['kind'], $quantity, $data, $data['note'] ?? null);
            $item->save();
        });

        return response()->json($this->payload((int) $data['company_id']));
    }

    public function export(Request $request)
    {
        $company = Company::query()->findOrFail($request->query('company_id'));
        $exportedAt = now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $location = $request->query('location', 'Gudang Utama');
        $officer = $request->query('officer', 'Tim Gosyen');
        $filename = 'stock-opname-'.$company->code_prefix.'-'.now()->format('Y-m-d').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($company, $exportedAt, $location, $officer): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Tanggal Export', 'Company', 'Lokasi', 'Petugas', 'Kode', 'Nama Barang', 'Tipe', 'Satuan', 'Stok Sistem', 'Stok Fisik', 'Selisih', 'Status']);

            StockItem::query()->where('company_id', $company->id)->orderBy('type')->orderBy('name')->each(function (StockItem $item) use ($handle, $company, $exportedAt, $location, $officer): void {
                $diff = $item->actual_stock - $item->system_stock;
                fputcsv($handle, [
                    $exportedAt,
                    $company->name,
                    $location,
                    $officer,
                    $item->code,
                    $item->name,
                    $item->type,
                    $item->unit,
                    $item->system_stock,
                    $item->actual_stock,
                    $diff,
                    $this->statusLabel($diff),
                ]);
            });

            fclose($handle);
        }, $filename, $headers);
    }

    public function storeCompany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:companies,name'],
        ]);

        $company = Company::create([
            'name' => $data['name'],
            'code_prefix' => $this->nextCompanyPrefix($data['name']),
        ]);

        return response()->json($this->payload($company->id));
    }

    private function payload(?int $companyId = null): array
    {
        $companyId = $companyId ?: Company::query()->orderBy('name')->value('id');

        $items = StockItem::query()
            ->where('company_id', $companyId)
            ->orderBy('type')
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
            ->with('stockItem:id,name,unit')
            ->whereHas('stockItem', fn ($query) => $query->where('company_id', $companyId))
            ->latest()
            ->limit(20)
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
                'note' => $movement->note,
                'at' => $movement->created_at?->toISOString(),
            ]);

        return [
            'companies' => Company::query()->orderBy('name')->get(['id', 'name', 'code_prefix']),
            'currentCompanyId' => $companyId,
            'products' => $items,
            'activities' => $movements,
        ];
    }

    private function recordMovement(StockItem $item, string $kind, int $quantity, array $data, ?string $note): void
    {
        StockMovement::create([
            'stock_item_id' => $item->id,
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

    private function nextCode(Company $company): string
    {
        $nextId = StockItem::query()->where('company_id', $company->id)->count() + 1;

        do {
            $code = sprintf('%s-%03d', $company->code_prefix, $nextId);
            $nextId++;
        } while (StockItem::query()->where('code', $code)->exists());

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
}
