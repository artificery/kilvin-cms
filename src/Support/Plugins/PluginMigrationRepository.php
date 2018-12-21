<?php

namespace Kilvin\Support\Plugins;

use Illuminate\Database\Migrations\DatabaseMigrationRepository;

class PluginMigrationRepository extends DatabaseMigrationRepository
{
    private $plugin;

    /**
     * Get a query builder for the migration table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function table()
    {
        return $this->getConnection()
            ->table($this->table)
            ->useWritePdo()
            ->where('plugin', $this->plugin());
    }

            /**
     * Log that a migration was run.
     *
     * @param  string  $file
     * @param  int     $batch
     * @return void
     */
    public function log($file, $batch)
    {
        $record = ['migration' => $file, 'batch' => $batch, 'plugin' => $this->plugin()];

        $this->table()->insert($record);
    }

    /**
     * Set the Plugin Name
     *
     * @param string $plugin
     * @return void
     */
    public function setPlugin($plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Get the Plugin Name
     *
     * @return string
     */
    public function plugin()
    {
        return $this->plugin;
    }
}
