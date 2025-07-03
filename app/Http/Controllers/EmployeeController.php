<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nomor' => 'required|unique:employees',
            'nama' => 'required',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png'
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('photos', 's3');
            $url = 'https://' . config('filesystems.disks.s3.bucket') . '.s3.' . config('filesystems.disks.s3.region') . '.amazonaws.com/' . $path;
        } else {
            $url = null;
        }

        $employee = Employee::create([
            'nomor' => $validated['nomor'],
            'nama' => $validated['nama'],
            'jabatan' => $request->jabatan,
            'talahir' => $request->talahir,
            'photo_upload_path' => $url,
            'created_on' => Carbon::now(),
            'created_by' => 'wahyu' // misalnya ambil dari auth()->user()->name
        ]);

        // Set Redis emp_<nomor>
        Redis::set("emp_{$employee->nomor}", $employee->toJson());

        return response()->json(['success' => true, 'data' => $employee]);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('photos', 's3');
            $url = 'https://' . config('filesystems.disks.s3.bucket') . '.s3.' . config('filesystems.disks.s3.region') . '.amazonaws.com/' . $path;
            $employee->photo_upload_path = $url;
        }

        $employee->nama = $request->nama ?? $employee->nama;
        $employee->jabatan = $request->jabatan ?? $employee->jabatan;
        $employee->updated_on = Carbon::now();
        $employee->updated_by = 'wahyu';

        $employee->save();

        // Update Redis
        Redis::set("emp_{$employee->nomor}", $employee->toJson());

        return response()->json(['success' => true, 'data' => $employee]);
    }

    public function show($id)
    {
        $employee = Employee::findOrFail($id);
        return response()->json($employee);
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->deleted_on = Carbon::now();
        $employee->save();

        // Hapus Redis
        Redis::del("emp_{$employee->nomor}");

        return response()->json(['deleted' => true]);
    }
}

