<?php

function where_to_set_clause($where_clause) {
  return str_replace(['NOT ', '<=>', ' OR'], ['', '=', ','], $where_clause);
}

function where_to_select_clause($where_clause) {
  return str_replace(['NOT ', '<=> new.', ' OR'], ['', 'as old_', ','], $where_clause);
}

function join_clause(&$id) {

  if (is_array($id)) {
    $join = 'old.'.$id[0].' = new.'.$id[0].' AND old.'.$id[1].' = new.'.$id[1];
    $id   = $id[0]; //fix id so it works for where clause
    return $join;
  }

  return "old.$id = new.$id";
}

function get_deleted_sql($new, $old, $id) {

  $join = join_clause($id);

  return "
    SELECT
      old.*
    FROM
      $new as new
    RIGHT JOIN $old as old ON
      $join
    WHERE
      new.$id IS NULL
  ";
}

function set_deleted_sql($new, $old, $id) {

  $join = join_clause($id);

  return "
    DELETE
      old
    FROM
      $new as new
    RIGHT JOIN $old as old ON
      $join
    WHERE
      new.$id IS NULL
  ";
}

function get_created_sql($new, $old, $id) {

  $join = join_clause($id);

  return "
    SELECT
      new.*
    FROM
      $new as new
    LEFT JOIN $old as old ON
      $join
    WHERE
      old.$id IS NULL
  ";
}

function set_created_sql($new, $old, $id) {

  $join = join_clause($id);

  return "
    INSERT INTO
      $old
    SELECT
      new.*
    FROM
      $new as new
    LEFT JOIN $old as old ON
      $join
    WHERE
      old.$id IS NULL
  ";
}

function get_updated_sql($new, $old, $id, $where_changes) {

  $join = join_clause($id);
  $select = where_to_select_clause($where_changes);

  $sql = "
    SELECT
      new.*,
      $select
    FROM
      $new as new
    JOIN $old as old ON
      $join
    WHERE $where_changes
  ";

  //email("CRON: get_updated_sql", $sql);

  return $sql;
}

function set_updated_sql($new, $old, $id, $where_changes) {

  $join = join_clause($id);
  $set  = where_to_set_clause($where_changes);

  $sql = "
    UPDATE
      $old as old
    JOIN $new as new ON
      $join
    SET
      $set
    WHERE
      $where_changes
  ";

  //email("CRON: set_updated_sql", $sql);

  return $sql;
}

function changed_fields($updated) {
  $changes = [];
  foreach($updated as $old_key => $old_val) {
    if (strpos($old_key, 'old_') !== false) {
      $new_key = substr($old_key, 4);
      $new_val = $updated[$new_key];
      if ($old_val != $new_val) {
        $old_val = is_null($old_val) ? 'NULL' : $old_val;
        $changes[] = "$new_key: $old_val >>> $new_val";
      }
    }
  }
  return $changes;
}
