<?php /** @noinspection SpellCheckingInspection */


class QuatroLogger
{


    public static function makeRecord($message, $type) {
        global $wpdb;
        $table =  "{$wpdb->base_prefix}_quatro_log";
        $wpdb->insert($table,array('ts' => (new DateTime())->format('Y-m-d T:i:s'),'message' => $message, 'type' => $type));
    }

    public static function getRecords() {
        global $wpdb;
        $table =  "{$wpdb->base_prefix}_quatro_log";
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 10;", ARRAY_A );
    }
}