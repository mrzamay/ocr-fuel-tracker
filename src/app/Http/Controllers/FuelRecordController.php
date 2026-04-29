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
        $records = $request->user()->fuelRecords()->orderBy('date', 'desc')->get();
        return response()->json($records);
    }

    // Создать новую запись (ручную или загрузка чека)
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'date' => 'nullable|date',
            'receipt_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $imagePath = null;
        $status = 'manual';
        $amount = $request->amount;
        $volume = $request->volume;

        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $imagePath = $file->storeAs('receipts', $fileName, 'public');
            
            // Отправка запроса к OCR микросервису
            try {
                // Обращаемся по имени сервиса в docker-compose: 'ocr:8000'
                $response = Http::attach(
                    'file', 
                    file_get_contents($file->getRealPath()), 
                    $file->getClientOriginalName()
                )->post('http://ocr:8000/recognize');

                if ($response->successful()) {
                    $ocrData = $response->json('extracted');
                    
                    // Если удалось вытащить данные - обновляем их и ставим статус success
                    if (!empty($ocrData['amount'])) {
                        $amount = $ocrData['amount'];
                    }
                    if (!empty($ocrData['volume'])) {
                        $volume = $ocrData['volume'];
                    }
                    
                    $status = ($amount || $volume) ? 'success' : 'ocr_pending';
                } else {
                    Log::error('OCR Service error: ' . $response->body());
                    $status = 'ocr_pending'; // Оставляем статус ожидания, если что-то пошло не так
                }
            } catch (\Exception $e) {
                Log::error('Failed to connect to OCR service: ' . $e->getMessage());
                $status = 'ocr_pending';
            }
        }

        $record = $request->user()->fuelRecords()->create([
            'amount' => $amount,
            'volume' => $volume,
            'date' => $request->date ?? now()->toDateString(),
            'receipt_image_path' => $imagePath,
            'status' => $status,
        ]);

        return response()->json([
            'message' => 'Запись успешно создана',
            'data' => $record
        ], 201);
    }

    // Обновить существующую запись (полезно для ручной корректировки OCR)
    public function update(Request $request, FuelRecord $fuelRecord)
    {
        // Проверяем, принадлежит ли запись текущему пользователю
        if ($request->user()->id !== $fuelRecord->user_id) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'date' => 'nullable|date',
            'status' => 'nullable|string|in:manual,success,ocr_pending'
        ]);

        $fuelRecord->update($request->only(['amount', 'volume', 'date', 'status']));

        return response()->json([
            'message' => 'Запись обновлена',
            'data' => $fuelRecord
        ]);
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
