<?php

namespace App\Services;

use App\Models\Program;
use App\Models\ProgramTujuan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProgramTujuanService
{
    /**
     * Sinkronkan repeater tujuan program beserta foto-fotonya.
     *
     * Baris yang sudah tidak dikirim dianggap dihapus. ID selalu dicari melalui
     * relasi program agar tujuan milik program lain tidak dapat diubah.
     */
    public function sync(Request $request, Program $program): void
    {
        if (! $request->has('tujuan')) {
            return;
        }

        $retainedIds = [];

        $order = 0;

        foreach ($request->input('tujuan', []) as $inputIndex => $payload) {
            $description = trim((string) ($payload['deskripsi'] ?? ''));

            if ($description === '') {
                continue;
            }

            $goal = isset($payload['id'])
                ? $program->tujuan()->whereKey($payload['id'])->first()
                : null;
            $oldPhotoPath = $goal?->foto_path;
            $newPhoto = $request->file("tujuan.{$inputIndex}.foto");
            $removePhoto = filter_var($payload['hapus_foto'] ?? false, FILTER_VALIDATE_BOOL);
            $photoPath = $oldPhotoPath;

            if ($newPhoto) {
                $photoPath = $newPhoto->store("programs/{$program->id}/tujuan", 'public');
            } elseif ($removePhoto) {
                $photoPath = null;
            }

            $values = [
                'foto_path' => $photoPath,
                'deskripsi' => $description,
                'urutan' => $order++,
            ];

            if ($goal) {
                $goal->update($values);
            } else {
                $goal = $program->tujuan()->create($values);
            }

            $retainedIds[] = $goal->id;

            if ($oldPhotoPath && $oldPhotoPath !== $photoPath) {
                Storage::disk('public')->delete($oldPhotoPath);
            }
        }

        $removedGoals = $program->tujuan()
            ->when($retainedIds !== [], fn ($query) => $query->whereNotIn('id', $retainedIds))
            ->get();

        $removedGoals->each(function (ProgramTujuan $goal): void {
            if ($goal->foto_path) {
                Storage::disk('public')->delete($goal->foto_path);
            }

            $goal->delete();
        });
    }
}
