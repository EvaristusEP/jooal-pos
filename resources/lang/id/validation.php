<?php

return [
    'accepted' => ':attribute harus disetujui.',
    'after' => ':attribute harus berupa tanggal setelah :date.',
    'after_or_equal' => ':attribute harus berupa tanggal pada atau setelah :date.',
    'boolean' => ':attribute harus bernilai benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':attribute bukan tanggal yang valid.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'max' => ['numeric' => ':attribute maksimal :max.', 'string' => ':attribute maksimal :max karakter.', 'array' => ':attribute maksimal :max item.'],
    'min' => ['numeric' => ':attribute minimal :min.', 'string' => ':attribute minimal :min karakter.', 'array' => ':attribute minimal :min item.'],
    'numeric' => ':attribute harus berupa angka.',
    'required' => ':attribute wajib diisi.',
    'unique' => ':attribute sudah digunakan.',
    'attributes' => [
        'name' => 'nama', 'email' => 'email', 'phone' => 'nomor telepon', 'quantity' => 'jumlah',
        'price' => 'harga', 'password' => 'kata sandi', 'branch_id' => 'cabang', 'ingredient_id' => 'bahan baku',
        'menu_id' => 'produk', 'locale' => 'bahasa',
    ],
];
