<#1>
<?php
    $table_name = "dci_cache";

    if (! $ilDB->tableExists($table_name)) {
    $fields = [
        'cache_id'   => [
            'type'    => 'integer',
            'length'  => 8,
            'notnull' => true,
        ],
        'type'       => [
            'type'    => 'text', // VARCHAR
            'length'  => 64,
            'notnull' => true,
        ],
        'user_id'    => [
            'type'    => 'integer',
            'length'  => 4,
            'notnull' => false,
        ],
        'object_id'  => [
            'type'    => 'integer',
            'length'  => 8,
            'notnull' => false,
        ],
        'updated_at' => [
            'type'    => 'timestamp',
            'notnull' => true,
        ],
        'value'      => [
            'type'    => 'clob', // TEXT
            'notnull' => true,
        ],
    ];

    $ilDB->createTable($table_name, $fields);
    $ilDB->addPrimaryKey($table_name, ["cache_id"]);
    $ilDB->query("ALTER TABLE `dci_cache` CHANGE COLUMN `cache_id` `cache_id` BIGINT(20) NOT NULL AUTO_INCREMENT FIRST");
    $ilDB->query("ALTER TABLE `dci_cache`	ADD UNIQUE INDEX `UNIQUE KEY` (`type`, `user_id`, `object_id`)");
}
?>