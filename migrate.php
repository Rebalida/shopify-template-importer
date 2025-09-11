<?php
// Run all migration files in order
foreach (glob(__DIR__ . '/migrations/*.php') as $migration) {
    require $migration;
}

