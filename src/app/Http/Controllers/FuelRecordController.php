<?php

namespace App\Http\Controllers;

use App\Models\FuelRecord;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FuelRecordController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()
            ->fuelRecords()
            ->with('vehicle')
            ->orderBy('date')
            ->orderBy('id');

        $this->applyFilters($query, $request);

        $records = $this->withAnalytics($query->get())->reverse()->values();

        return response()->json($records);
    }

    public function meta(Request $request)
    {
        $records = $request->user()->fuelRecords();

        return response()->json([
            'stations' => (clone $records)
                ->whereNotNull('station_name')
                ->distinct()
                ->orderBy('station_name')
                ->pluck('station_name')
                ->values(),
            'fuel_types' => (clone $records)
                ->whereNotNull('fuel_type')
                ->distinct()
                ->orderBy('fuel_type')
                ->pluck('fuel_type')
                ->values(),
            'monthly' => $this->monthlyStats($request),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $vehicle = $this->resolveVehicle($request, $validated['vehicle_id'] ?? null);

        $imagePath = null;
        $status = 'manual';
        $amount = $validated['amount'] ?? null;
        $volume = $validated['volume'] ?? null;
        $unitPrice = $validated['unit_price'] ?? null;
        $fuelType = $validated['fuel_type'] ?? $vehicle?->fuel_type;
        $stationName = $validated['station_name'] ?? null;
        $ocrPayload = null;

        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $imagePath = $file->storeAs('receipts', $fileName, 'public');

            try {
                $response = Http::timeout(90)->attach(
                    'file',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName()
                )->post('http://ocr:8000/recognize');

                if ($response->successful()) {
                    $ocrPayload = $response->json();
                    $ocrData = $ocrPayload['extracted'] ?? [];

                    $amount = $ocrData['amount'] ?? $amount;
                    $volume = $ocrData['volume'] ?? $volume;
                    $unitPrice = $ocrData['unit_price'] ?? $unitPrice;
                    $fuelType = $ocrData['fuel_type'] ?? $fuelType;
                    $stationName = $ocrData['station_name'] ?? $stationName;
                    $status = ($amount || $volume) ? 'success' : 'ocr_pending';
                } else {
                    $status = 'ocr_pending';
                }
            } catch (\Throwable) {
                $status = 'ocr_pending';
            }
        }

        $unitPrice = $this->resolveUnitPrice($amount, $volume, $unitPrice);

        $record = $request->user()->fuelRecords()->create([
            'vehicle_id' => $vehicle?->id,
            'amount' => $amount,
            'volume' => $volume,
            'unit_price' => $unitPrice,
            'is_full_tank' => $validated['is_full_tank'] ?? true,
            'odometer_km' => $validated['odometer_km'] ?? null,
            'date' => $validated['date'] ?? now()->toDateString(),
            'receipt_image_path' => $imagePath,
            'status' => $status,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'location_accuracy' => $validated['location_accuracy'] ?? null,
            'station_name' => $stationName,
            'fuel_type' => $fuelType,
            'ocr_meta' => $ocrPayload ? [
                'ocr' => $ocrPayload['ocr'] ?? null,
                'extracted' => $ocrPayload['extracted'] ?? null,
                'raw_text' => $ocrPayload['raw_text'] ?? null,
            ] : null,
        ]);

        $this->syncVehicleOdometer($record);

        return response()->json([
            'message' => 'Запись успешно создана',
            'data' => $record->load('vehicle'),
            'raw_text' => $ocrPayload['raw_text'] ?? null,
            'ocr' => $ocrPayload['ocr'] ?? null,
            'ocr_extracted' => $ocrPayload['extracted'] ?? null,
        ], 201);
    }

    public function update(Request $request, FuelRecord $fuelRecord)
    {
        if ($request->user()->id !== $fuelRecord->user_id) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        $validated = $this->validatePayload($request, false);
        $vehicle = $this->resolveVehicle($request, $validated['vehicle_id'] ?? $fuelRecord->vehicle_id);

        $validated['vehicle_id'] = $vehicle?->id;
        $validated['unit_price'] = $this->resolveUnitPrice(
            $validated['amount'] ?? $fuelRecord->amount,
            $validated['volume'] ?? $fuelRecord->volume,
            $validated['unit_price'] ?? $fuelRecord->unit_price
        );

        $fuelRecord->update($validated);
        $this->syncVehicleOdometer($fuelRecord->fresh());

        return response()->json([
            'message' => 'Запись обновлена',
            'data' => $fuelRecord->fresh()->load('vehicle'),
        ]);
    }

    public function destroy(Request $request, FuelRecord $fuelRecord)
    {
        if ($request->user()->id !== $fuelRecord->user_id) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        if ($fuelRecord->receipt_image_path) {
            Storage::disk('public')->delete($fuelRecord->receipt_image_path);
        }

        $fuelRecord->delete();

        return response()->json(['message' => 'Запись удалена']);
    }

    private function validatePayload(Request $request, bool $withImage = true): array
    {
        return $request->validate([
            'vehicle_id' => [
                'nullable',
                'integer',
                Rule::exists('vehicles', 'id')->where('user_id', $request->user()->id),
            ],
            'amount' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'is_full_tank' => 'nullable|boolean',
            'odometer_km' => 'nullable|integer|min:0',
            'date' => 'nullable|date',
            'receipt_image' => [$withImage ? 'nullable' : 'prohibited', 'file', 'extensions:jpg,jpeg,png,webp,heic,heif', 'max:30720'],
            'station_name' => 'nullable|string|max:255',
            'fuel_type' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:manual,success,ocr_pending',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location_accuracy' => 'nullable|string|max:255',
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        $query
            ->when($request->vehicle_id, fn ($q, $value) => $q->where('vehicle_id', $value))
            ->when($request->fuel_type, fn ($q, $value) => $q->where('fuel_type', $value))
            ->when($request->station_name, fn ($q, $value) => $q->where('station_name', $value))
            ->when($request->status === 'ocr_pending', fn ($q) => $q->where('status', 'ocr_pending'))
            ->when($request->month, function ($q, $value) {
                [$year, $month] = array_pad(explode('-', $value), 2, null);
                if ($year && $month) {
                    $q->whereYear('date', $year)->whereMonth('date', $month);
                }
            });
    }

    private function resolveVehicle(Request $request, ?int $vehicleId): ?Vehicle
    {
        if ($vehicleId) {
            return $request->user()->vehicles()->whereKey($vehicleId)->first();
        }

        return $request->user()->vehicles()->where('is_default', true)->first()
            ?? $request->user()->vehicles()->first()
            ?? $request->user()->vehicles()->create([
                'name' => 'Моё авто',
                'fuel_type' => 'АИ-95',
                'is_default' => true,
            ]);
    }

    private function resolveUnitPrice($amount, $volume, $unitPrice): ?float
    {
        if ($unitPrice) {
            return round((float) $unitPrice, 2);
        }

        if ($amount && $volume && (float) $volume > 0) {
            return round((float) $amount / (float) $volume, 2);
        }

        return null;
    }

    private function syncVehicleOdometer(FuelRecord $record): void
    {
        if (!$record->vehicle_id || !$record->odometer_km) {
            return;
        }

        Vehicle::whereKey($record->vehicle_id)
            ->where(function ($query) use ($record) {
                $query->whereNull('current_odometer_km')
                    ->orWhere('current_odometer_km', '<', $record->odometer_km);
            })
            ->update(['current_odometer_km' => $record->odometer_km]);
    }

    private function withAnalytics($records)
    {
        $state = [];

        return $records->map(function (FuelRecord $record) use (&$state) {
            $key = $record->vehicle_id ?: 'default';
            $state[$key] ??= [
                'previousFullOdometer' => null,
                'volumeSinceFull' => 0.0,
                'amountSinceFull' => 0.0,
            ];

            $record->distance_km = null;
            $record->consumption_l_per_100km = null;
            $record->cost_per_km = null;
            $warnings = [];

            $state[$key]['volumeSinceFull'] += (float) ($record->volume ?? 0);
            $state[$key]['amountSinceFull'] += (float) ($record->amount ?? 0);

            if ($record->is_full_tank && $record->odometer_km) {
                $previousFullOdometer = $state[$key]['previousFullOdometer'];

                if ($previousFullOdometer && $record->odometer_km > $previousFullOdometer) {
                    $distance = $record->odometer_km - $previousFullOdometer;
                    $record->distance_km = $distance;
                    $record->consumption_l_per_100km = round(($state[$key]['volumeSinceFull'] / $distance) * 100, 2);
                    $record->cost_per_km = round($state[$key]['amountSinceFull'] / $distance, 2);

                    if ($record->consumption_l_per_100km > 25 || $record->consumption_l_per_100km < 3) {
                        $warnings[] = 'Проверьте пробег или литры: расход выглядит необычно';
                    }
                }

                $state[$key]['previousFullOdometer'] = $record->odometer_km;
                $state[$key]['volumeSinceFull'] = 0.0;
                $state[$key]['amountSinceFull'] = 0.0;
            }

            $record->warnings = $warnings;

            return $record;
        });
    }

    private function monthlyStats(Request $request): array
    {
        $query = $request->user()->fuelRecords()->with('vehicle')->orderBy('date')->orderBy('id');
        $this->applyFilters($query, $request);

        $records = $this->withAnalytics($query->get());
        $month = $request->month ?: now()->format('Y-m');
        $monthly = $records->filter(fn ($record) => $record->date?->format('Y-m') === $month);

        $distance = $monthly->sum('distance_km');
        $amount = $monthly->sum(fn ($record) => (float) ($record->amount ?? 0));
        $volume = $monthly->sum(fn ($record) => (float) ($record->volume ?? 0));
        $prices = $monthly->pluck('unit_price')->filter();
        $consumptions = $monthly->pluck('consumption_l_per_100km')->filter();

        return [
            'month' => $month,
            'records_count' => $monthly->count(),
            'amount' => round($amount, 2),
            'volume' => round($volume, 2),
            'distance_km' => $distance ?: null,
            'avg_consumption' => $consumptions->count() ? round($consumptions->avg(), 2) : null,
            'avg_unit_price' => $prices->count() ? round($prices->avg(), 2) : null,
            'cost_per_km' => $distance ? round($amount / $distance, 2) : null,
        ];
    }
}
