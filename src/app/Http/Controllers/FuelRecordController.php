<?php

namespace App\Http\Controllers;

use App\Models\FuelRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class FuelRecordController extends Controller
{
    use AuthorizesRequests;

    // Получить все записи текущего пользователя
    public function index(Request $request)
    {
        $records = $request->user()
            ->fuelRecords()
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $previousOdometer = null;

        $records = $records->map(function (FuelRecord $record) use (&$previousOdometer) {
            $record->distance_km = null;
            $record->consumption_l_per_100km = null;

            if ($record->odometer_km && $previousOdometer && $record->odometer_km > $previousOdometer) {
                $distance = $record->odometer_km - $previousOdometer;
                $record->distance_km = $distance;

                if ($record->volume) {
                    $record->consumption_l_per_100km = round(((float) $record->volume / $distance) * 100, 2);
                }
            }

            if ($record->odometer_km) {
                $previousOdometer = $record->odometer_km;
            }

            return $record;
        })->reverse()->values();

        return response()->json($records);
    }

    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'odometer_km' => 'nullable|integer|min:0',
            'date' => 'nullable|date',
            'receipt_image' => 'nullable|file|extensions:jpg,jpeg,png,webp,heic,heif|max:30720',
            'station_name' => 'nullable|string|max:255',
            'fuel_type' => 'nullable|string|max:100',
        ]);

        $imagePath = null;
        $status = 'manual';
        $amount = $request->amount;
        $volume = $request->volume;
        $ocrPayload = null;

        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $imagePath = $file->storeAs('receipts', $fileName, 'public');
            
            try {
                $response = Http::timeout(90)->attach(
                    'file', file_get_contents($file->getRealPath()), $file->getClientOriginalName()
                )->post('http://ocr:8000/recognize');

                if ($response->successful()) {
                    $ocrPayload = $response->json();
                    $ocrData = $response->json('extracted');
                    if (!empty($ocrData['amount'])) $amount = $ocrData['amount'];
                    if (!empty($ocrData['volume'])) $volume = $ocrData['volume'];
                    $status = ($amount || $volume) ? 'success' : 'ocr_pending';
                } else {
                    $status = 'ocr_pending';
                }
            } catch (\Exception $e) {
                $status = 'ocr_pending';
            }
        }

        $record = $request->user()->fuelRecords()->create([
            'amount' => $amount,
            'volume' => $volume,
            'odometer_km' => $request->odometer_km,
            'date' => $request->date ?? now()->toDateString(),
            'receipt_image_path' => $imagePath,
            'status' => $status,
            'station_name' => $request->station_name, // <-- Добавлено
            'fuel_type' => $request->fuel_type,       // <-- Добавлено
        ]);

        return response()->json([
            'message' => 'Запись успешно создана',
            'data' => $record,
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

        $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'odometer_km' => 'nullable|integer|min:0',
            'date' => 'nullable|date',
            'status' => 'nullable|string|in:manual,success,ocr_pending',
            'station_name' => 'nullable|string|max:255',
            'fuel_type' => 'nullable|string|max:100',
        ]);

        $fuelRecord->update($request->only(['amount', 'volume', 'odometer_km', 'date', 'status', 'station_name', 'fuel_type']));

        return response()->json(['message' => 'Запись обновлена', 'data' => $fuelRecord]);
    }

    // Удалить запись и связанное изображение
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
}
