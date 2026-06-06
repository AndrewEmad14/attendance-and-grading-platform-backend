<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateDbml extends Command
{
  protected $signature = 'dbml:generate';
  protected $description = 'Generate DBML file from the current database';

  public function handle()
  {
    $dbml = '';
    $tables = $this->getTables();

    foreach ($tables as $table) {
      $dbml .= $this->tableDefinition($table);
      $dbml .= "\n";
      $dbml .= $this->tableReferences($table);
      $dbml .= "\n\n";
    }

    file_put_contents('schema.dbml', $dbml);
    $this->info('✅ DBML file generated: schema.dbml');
    $this->info('Copy the contents into https://dbdiagram.io');
  }

  private function getTables()
  {
    // Works with SQLite, MySQL, PostgreSQL
    $connection = DB::connection();
    $driver = $connection->getDriverName();

    if ($driver === 'sqlite') {
      $tables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
      return array_map(fn($t) => $t->name, $tables);
    }

    // MySQL / PostgreSQL fallback
    return Schema::getAllTables();
  }

  private function tableDefinition($table)
  {
    $columns = Schema::getColumnListing($table);
    $colDefs = [];

    foreach ($columns as $column) {
      $type = Schema::getColumnType($table, $column);
      $nullable = $this->isColumnNullable($table, $column);
      $isPrimary = $this->isPrimaryKey($table, $column);

      $dbmlType = $this->mapType($type);
      $def = "  $column $dbmlType";

      if ($isPrimary) {
        $def .= " pk";          // pk implies not null
      } else {
        if (!$nullable) $def .= " not null";
      }

      $colDefs[] = $def;
    }

    return "Table $table {\n" . implode("\n", $colDefs) . "\n}";
  }

  private function tableReferences($table)
  {
    $foreignKeys = DB::select($this->getForeignKeyQuery($table));
    $refs = [];
    foreach ($foreignKeys as $fk) {
      if ($driver = DB::connection()->getDriverName() === 'sqlite') {
        // SQLite PRAGMA
        $refs[] = "Ref: $table.{$fk->from} > {$fk->table}.{$fk->to}";
      } else {
        $refs[] = "Ref: $table.{$fk->COLUMN_NAME} > {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}";
      }
    }
    return implode("\n", $refs);
  }

  private function getForeignKeyQuery($table)
  {
    $driver = DB::connection()->getDriverName();
    if ($driver === 'sqlite') {
      return "PRAGMA foreign_key_list($table)";
    }
    // MySQL / PostgreSQL fallback (simplified)
    return "
            SELECT 
                kcu.COLUMN_NAME,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE kcu
            WHERE kcu.TABLE_NAME = '$table'
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ";
  }

  private function isColumnNullable($table, $column)
  {
    $driver = DB::connection()->getDriverName();
    if ($driver === 'sqlite') {
      $info = DB::select("PRAGMA table_info($table)");
      foreach ($info as $col) {
        if ($col->name === $column) {
          return $col->notnull == 0;
        }
      }
      return true;
    }
    // For other drivers, use Schema::hasColumn? Better to just rely on column type
    // We'll assume nullable by default
    return true;
  }

  private function isPrimaryKey($table, $column)
  {
    $driver = DB::connection()->getDriverName();
    if ($driver === 'sqlite') {
      $info = DB::select("PRAGMA table_info($table)");
      foreach ($info as $col) {
        if ($col->name === $column) {
          return $col->pk == 1;
        }
      }
      return false;
    }
    // MySQL/PostgreSQL: check primary key indexes
    $indexes = Schema::getIndexes($table);
    foreach ($indexes as $index) {
      if ($index['primary'] && in_array($column, $index['columns'])) {
        return true;
      }
    }
    return false;
  }

  private function mapType($type)
  {
    $map = [
      'integer' => 'int',
      'bigint' => 'bigint',
      'string' => 'varchar',
      'text' => 'text',
      'timestamp' => 'datetime',
      'datetime' => 'datetime',
      'decimal' => 'decimal',
      'float' => 'float',
      'boolean' => 'boolean',
      'date' => 'date',
      'time' => 'time',
    ];
    return $map[$type] ?? $type;
  }
}
