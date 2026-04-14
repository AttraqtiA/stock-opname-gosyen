<?php

namespace App\Http\Controllers;

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
        return response()->json($this->payload());
    }

    public function storeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:32'],
            'system_stock' => ['required', 'integer', 'min:0'],
            'actual_stock' => ['required', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'officer' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data): void {
            $item = StockItem::create([
                'code' => $this->nextCode($data['type']),
                'name' => $data['name'],
                'type' => $data['type'],
                'unit' => $data['unit'],
                'system_stock' => $data['system_stock'],
                'actual_stock' => $data['actual_stock'],
            ]);

            $this->recordMovement($item, 'create', $data['actual_stock'], $data, 'Barang baru');
        });

        return response()->json($this->payload(), 201);
    }

    public function storeMovement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'kind' => ['required', Rule::in(['in', 'out', 'count', 'sync'])],
            'quantity' => ['required', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'officer' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data): void {
            $item = StockItem::lockForUpdate()->findOrFail($data['stock_item_id']);
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

        return response()->json($this->payload());
    }

    public function export(Request $request)
    {
        $exportedAt = now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $location = $request->query('location', 'Gudang Utama');
        $officer = $request->query('officer', 'Tim Gosyen');
        $filename = 'stock-opname-gosyen-'.now()->format('Y-m-d').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($exportedAt, $location, $officer): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Tanggal Export', 'Lokasi', 'Petugas', 'Kode', 'Nama Barang', 'Tipe', 'Satuan', 'Stok Sistem', 'Stok Fisik', 'Selisih', 'Status']);

            StockItem::query()->orderBy('type')->orderBy('name')->each(function (StockItem $item) use ($handle, $exportedAt, $location, $officer): void {
                $diff = $item->actual_stock - $item->system_stock;
                fputcsv($handle, [
                    $exportedAt,
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

    private function payload(): array
    {
        $items = StockItem::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (StockItem $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'type' => $item->type,
                'unit' => $item->unit,
                'systemStock' => $item->system_stock,
                'actualStock' => $item->actual_stock,
                'updatedAt' => $item->updated_at?->toISOString(),
            ]);

        $movements = StockMovement::query()
            ->with('stockItem:id,name,unit')
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

    private function nextCode(string $type): string
    {
        $prefix = Str::of($type)
            ->explode(' ')
            ->map(fn (string $word): string => Str::substr($word, 0, 1))
            ->implode('');
        $prefix = Str::of($prefix)->upper()->substr(0, 3)->padRight(3, 'X')->toString();

        $nextId = (StockItem::max('id') ?? 0) + 1;

        return sprintf('GSY-%s-%03d', $prefix, $nextId);
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
