<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Вычисляемые колонки для курсорной пагинации в API v2.
 *
 * Курсор Laravel строит условие «после строки X» только по обычным колонкам:
 * выражение CASE (сортировка по важности) он пропускает, а NULL в сроке
 * ломает сравнение, и задачи без срока выпадают из выдачи. Виртуальные
 * колонки превращают оба случая в обычную сортировку по полю.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority_weight')
                ->virtualAs("CASE priority WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 WHEN 'urgent' THEN 4 ELSE 0 END");
            $table->date('due_date_sort')
                ->virtualAs("COALESCE(due_date, '9999-12-31')");

            $table->index(['project_id', 'priority_weight']);
            $table->index(['project_id', 'due_date_sort']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'priority_weight']);
            $table->dropIndex(['project_id', 'due_date_sort']);
            $table->dropColumn(['priority_weight', 'due_date_sort']);
        });
    }
};
