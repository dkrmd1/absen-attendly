<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\TaxDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeResourceController extends Controller
{
    public function announcements(): View
    {
        return view('admin.employee-resources.announcements', [
            'announcements' => Announcement::query()->latest()->paginate(25),
        ]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'category' => ['required', Rule::in(['berita', 'pengumuman'])],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'publish_now' => ['nullable', 'boolean'],
        ]);
        Announcement::query()->create([
            'category' => $attributes['category'],
            'title' => $attributes['title'],
            'body' => $attributes['body'],
            'published_at' => ($attributes['publish_now'] ?? false) ? now() : null,
            'published_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.announcements.index')->with('success', 'Berita berhasil disimpan.');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_if($announcement->archived_at, 404);
        $attributes = $request->validate([
            'category' => ['required', Rule::in(['berita', 'pengumuman'])],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'publish_now' => ['nullable', 'boolean'],
        ]);
        $announcement->update([
            'category' => $attributes['category'],
            'title' => $attributes['title'],
            'body' => $attributes['body'],
            'published_at' => ($attributes['publish_now'] ?? false)
                ? ($announcement->published_at ?? now())
                : null,
        ]);

        return redirect()->route('admin.announcements.index')->with('success', 'Informasi berhasil diperbarui.');
    }

    public function archiveAnnouncement(Announcement $announcement): RedirectResponse
    {
        $announcement->update([
            'published_at' => null,
            'archived_at' => now(),
        ]);

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Informasi berhasil diarsipkan.');
    }

    public function restoreAnnouncement(Announcement $announcement): RedirectResponse
    {
        $announcement->update(['archived_at' => null]);

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Informasi berhasil dipulihkan sebagai draft.');
    }

    public function taxDocuments(): View
    {
        return view('admin.employee-resources.tax-documents', [
            'employees' => User::query()->staff()->orderBy('name')->get(['id', 'name', 'email']),
            'documents' => TaxDocument::query()->with('user:id,name,email')->latest('tax_year')->paginate(25),
        ]);
    }

    public function storeTaxDocument(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'tax_year' => ['required', 'integer', 'between:2000,2100'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'publish_now' => ['nullable', 'boolean'],
        ]);
        abort_unless(User::query()->staff()->whereKey($attributes['user_id'])->exists(), 422);

        $path = $request->file('document')->store('private/tax-documents', 'local');
        if (! $path) {
            throw new \RuntimeException('Dokumen SPT gagal disimpan ke penyimpanan privat.');
        }

        try {
            TaxDocument::query()->create([
                'user_id' => $attributes['user_id'],
                'tax_year' => $attributes['tax_year'],
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'file_path' => $path,
                'uploaded_by' => $request->user()->id,
                'visible_at' => ($attributes['publish_now'] ?? false) ? now() : null,
            ]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return redirect()->route('admin.tax-documents.index')
            ->with('success', 'Dokumen SPT berhasil disimpan.');
    }

    public function updateTaxDocument(Request $request, TaxDocument $taxDocument): RedirectResponse
    {
        abort_if($taxDocument->archived_at, 404);
        $attributes = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'tax_year' => ['required', 'integer', 'between:2000,2100'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'publish_now' => ['nullable', 'boolean'],
        ]);
        abort_unless(User::query()->staff()->whereKey($attributes['user_id'])->exists(), 422);

        $newPath = $request->file('document')?->store('private/tax-documents', 'local');
        if ($request->hasFile('document') && ! $newPath) {
            throw new \RuntimeException('Dokumen SPT gagal disimpan ke penyimpanan privat.');
        }

        $oldPath = $taxDocument->file_path;
        try {
            $taxDocument->update([
                'user_id' => $attributes['user_id'],
                'tax_year' => $attributes['tax_year'],
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'file_path' => $newPath ?? $oldPath,
                'visible_at' => ($attributes['publish_now'] ?? false)
                    ? ($taxDocument->visible_at ?? now())
                    : null,
            ]);
        } catch (\Throwable $error) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }

            throw $error;
        }

        if ($newPath && $newPath !== $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('admin.tax-documents.index')
            ->with('success', 'Dokumen SPT berhasil diperbarui.');
    }

    public function taxDocumentFile(TaxDocument $taxDocument): StreamedResponse
    {
        abort_if($taxDocument->archived_at, 404);

        return Storage::disk('local')->download($taxDocument->file_path);
    }

    public function publishTaxDocument(TaxDocument $taxDocument): RedirectResponse
    {
        abort_if($taxDocument->archived_at, 404);
        $taxDocument->update(['visible_at' => $taxDocument->visible_at ?? now()]);

        return redirect()->route('admin.tax-documents.index')
            ->with('success', 'Dokumen SPT berhasil diterbitkan ke akun pemilik.');
    }

    public function archiveTaxDocument(TaxDocument $taxDocument): RedirectResponse
    {
        $taxDocument->update([
            'visible_at' => null,
            'archived_at' => now(),
        ]);

        return redirect()->route('admin.tax-documents.index')
            ->with('success', 'Dokumen SPT berhasil diarsipkan.');
    }

    public function restoreTaxDocument(TaxDocument $taxDocument): RedirectResponse
    {
        $taxDocument->update(['archived_at' => null]);

        return redirect()->route('admin.tax-documents.index')
            ->with('success', 'Dokumen SPT berhasil dipulihkan sebagai draft.');
    }
}
