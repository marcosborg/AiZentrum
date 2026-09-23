<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('voice_complaints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->text('payload');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('voice_complaints'); }
};
