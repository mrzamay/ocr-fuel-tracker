<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()
                ->vehicles()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'plate_number' => 'nullable|string|max:32',
            'fuel_type' => 'nullable|string|max:100',
            'current_odometer_km' => 'nullable|integer|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            $request->user()->vehicles()->update(['is_default' => false]);
        }

        $vehicle = $request->user()->vehicles()->create([
            ...$validated,
            'is_default' => (bool) ($validated['is_default'] ?? $request->user()->vehicles()->doesntExist()),
        ]);

        return response()->json($vehicle, 201);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'plate_number' => 'nullable|string|max:32',
            'fuel_type' => 'nullable|string|max:100',
            'current_odometer_km' => 'nullable|integer|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            $request->user()->vehicles()->whereKeyNot($vehicle->id)->update(['is_default' => false]);
        }

        $vehicle->update($validated);

        return response()->json($vehicle);
    }

    public function destroy(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        if ($vehicle->fuelRecords()->exists()) {
            return response()->json(['message' => 'У авто есть заправки, удаление недоступно'], 422);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Авто удалено']);
    }
}
