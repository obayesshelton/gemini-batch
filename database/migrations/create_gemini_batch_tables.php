<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ObayesShelton\GeminiBatch\Models\GeminiBatch;
use ObayesShelton\GeminiBatch\Models\GeminiBatchRequest;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('gemini-batch.tables.batches', GeminiBatch::TABLE), function (Blueprint $table) {
            $table->id();
            $table->string(GeminiBatch::COLUMN_API_BATCH_NAME)->nullable()->index();
            $table->string(GeminiBatch::COLUMN_MODEL);
            $table->string(GeminiBatch::COLUMN_DISPLAY_NAME)->nullable();
            $table->string(GeminiBatch::COLUMN_STATE)->default('pending')->index();
            $table->string(GeminiBatch::COLUMN_INPUT_MODE)->nullable();
            $table->unsignedInteger(GeminiBatch::COLUMN_TOTAL_REQUESTS)->default(0);
            $table->unsignedInteger(GeminiBatch::COLUMN_COMPLETED_REQUESTS)->default(0);
            $table->unsignedInteger(GeminiBatch::COLUMN_FAILED_REQUESTS)->default(0);
            $table->string(GeminiBatch::COLUMN_ON_COMPLETED_HANDLER)->nullable();
            $table->string(GeminiBatch::COLUMN_ON_EACH_RESULT_HANDLER)->nullable();
            $table->json(GeminiBatch::COLUMN_METADATA)->nullable();
            $table->text(GeminiBatch::COLUMN_ERROR_MESSAGE)->nullable();
            $table->string(GeminiBatch::COLUMN_QUEUE)->nullable();
            $table->string(GeminiBatch::COLUMN_CONNECTION)->nullable();
            $table->timestamp(GeminiBatch::COLUMN_SUBMITTED_AT)->nullable();
            $table->timestamp(GeminiBatch::COLUMN_COMPLETED_AT)->nullable();
            $table->timestamps();
        });

        Schema::create(config('gemini-batch.tables.requests', GeminiBatchRequest::TABLE), function (Blueprint $table) {
            $table->id();
            $table->foreignId(GeminiBatchRequest::COLUMN_GEMINI_BATCH_ID)
                ->constrained(config('gemini-batch.tables.batches', GeminiBatch::TABLE))
                ->cascadeOnDelete();
            $table->string(GeminiBatchRequest::COLUMN_KEY)->index();
            $table->string(GeminiBatchRequest::COLUMN_STATE)->default('pending');
            $table->json(GeminiBatchRequest::COLUMN_REQUEST_PAYLOAD);
            $table->json(GeminiBatchRequest::COLUMN_RESPONSE_PAYLOAD)->nullable();
            $table->longText(GeminiBatchRequest::COLUMN_RESPONSE_TEXT)->nullable();
            $table->json(GeminiBatchRequest::COLUMN_STRUCTURED_RESPONSE)->nullable();
            $table->json(GeminiBatchRequest::COLUMN_META)->nullable();
            $table->unsignedInteger(GeminiBatchRequest::COLUMN_PROMPT_TOKENS)->nullable();
            $table->unsignedInteger(GeminiBatchRequest::COLUMN_COMPLETION_TOKENS)->nullable();
            $table->unsignedInteger(GeminiBatchRequest::COLUMN_THOUGHT_TOKENS)->nullable();
            $table->text(GeminiBatchRequest::COLUMN_ERROR_MESSAGE)->nullable();
            $table->timestamp(GeminiBatchRequest::COLUMN_COMPLETED_AT)->nullable();
            $table->timestamps();

            $table->unique([GeminiBatchRequest::COLUMN_GEMINI_BATCH_ID, GeminiBatchRequest::COLUMN_KEY]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('gemini-batch.tables.requests', GeminiBatchRequest::TABLE));
        Schema::dropIfExists(config('gemini-batch.tables.batches', GeminiBatch::TABLE));
    }
};
