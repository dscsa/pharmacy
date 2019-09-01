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
      new.*
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

  return "
    SELECT
      new.*,
      $select
    FROM
      $new as new
    JOIN $old as old ON
      $join
    WHERE $where_changes
  ";
}

function set_updated_sql($new, $old, $id, $where_changes) {

  $join = join_clause($id);
  $set  = where_to_set_clause($where_changes);

  return "
    UPDATE
      $old as old
    LEFT JOIN $new as new ON
      $join
    SET
      $set
    WHERE
      $where_changes
  ";
}
