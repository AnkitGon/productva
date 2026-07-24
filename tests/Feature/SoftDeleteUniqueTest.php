<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps soft-delete aware unique indexes after migrations finish', function () {
    $codeIndex = DB::selectOne("SELECT sql FROM sqlite_master WHERE name = 'employees_code_unique'");

    expect($codeIndex->sql)->toContain('WHERE deleted_at IS NULL');
});
