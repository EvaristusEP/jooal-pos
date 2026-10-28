<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(): View
    {
        $tags = Tag::query()->withCount('menus')->orderBy('name')->get();
        return view('tags.index', compact('tags'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $this->assertSlugAvailable($data['slug']);
        Tag::create($data + ['is_active' => true]);
        return redirect()->route('tags.index')->with('success', 'Tag berhasil ditambahkan.');
    }

    public function update(Request $request, Tag $tag)
    {
        $data = $this->validated($request, $tag);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $this->assertSlugAvailable($data['slug'], $tag);
        $tag->update($data);
        return redirect()->route('tags.index')->with('success', 'Tag berhasil diperbarui.');
    }

    public function toggleStatus(Tag $tag)
    {
        $tag->update(['is_active' => ! $tag->is_active]);
        return redirect()->route('tags.index')->with('success', 'Status tag diperbarui.');
    }

    private function validated(Request $request, ?Tag $tag = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('tags', 'name')->ignore(optional($tag)->id)],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('tags', 'slug')->ignore(optional($tag)->id)],
        ], ['name.required' => 'Nama tag wajib diisi.']);
    }

    private function assertSlugAvailable(string $slug, ?Tag $tag = null): void
    {
        $query = Tag::query()->where('slug', $slug);
        if ($tag) $query->where('id', '<>', $tag->getKey());
        if ($query->exists()) {
            throw ValidationException::withMessages(['slug' => 'Slug tag sudah digunakan.']);
        }
    }
}
