<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Nullable at the schema level (a unique index still allows any number of NULLs) so this
        // migration can add the column without the doctrine/dbal package this environment doesn't
        // have installed, which Laravel's column ->change() requires. "Always present" is enforced
        // at the application layer instead — every write path (registration, the profile form,
        // and this migration's own backfill below) always sets it.
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        // Backfill any pre-existing rows (e.g. a database seeded before this migration) with a
        // derived, unique username.
        $taken = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'name', 'email']) as $user) {
            $base = Str::slug(Str::before($user->email, '@'), '') ?: Str::slug($user->name, '');
            $base = $base ?: 'member';
            $candidate = $base;
            $suffix = 1;

            while (in_array($candidate, $taken, true) || DB::table('users')->where('username', $candidate)->exists()) {
                $candidate = $base.$suffix;
                $suffix++;
            }

            $taken[] = $candidate;
            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
