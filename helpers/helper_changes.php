<?php

function where_to_set_clause($where_clause) {
  return str_replace(['NOT ', '<=>', ' OR'], ['', '=', ','], $where_clause);
}

function where_to_select_clause($where_clause) {
  return str_replace(['NOT ', '<=> new.', ' OR'], ['', 'as old_', ','], $where_clause);
}

function get_deleted_sql($new, $old, $id) {
  return "
    SELECT
      new.*
    FROM
      $new as new
    RIGHT JOIN $old as old ON
      old.$id = new.$id
    WHERE
      new.$id IS NULL
  ";
}

function set_deleted_sql($new, $old, $id) {
  return "
    DELETE
      old
    FROM
      $new as new
    RIGHT JOIN $old as old ON
      old.$id = new.$id
    WHERE
      new.$id IS NULL
  ";
}

function get_created_sql($new, $old, $id) {
  return "
    SELECT
      new.*
    FROM
      $new as new
    LEFT JOIN $old as old ON
      old.$id = new.$id
    WHERE
      old.$id IS NULL
  ";
}

function set_created_sql($new, $old, $id) {
  return "
    INSERT INTO
      $old
    SELECT
      new.*
    FROM
      $new as new
    LEFT JOIN gp_orders as old ON
      old.$id = new.$id
    WHERE
      old.$id IS NULL
  ";
}

function get_updated_sql($new, $old, $id, $where_changes) {

  $select = where_to_select_clause($where_changes);

  return "
    SELECT
      new.*,
      $select
    FROM
      $new as new
    JOIN $old as old ON
      old.$id = new.$id
    WHERE $where_changes
  ";
}

function set_updated_sql($new, $old, $id, $where_changes) {

  $set = where_to_set_clause($where_changes);

  return "
    UPDATE
      $old as old
    LEFT JOIN $new as new ON
      old.$id = new.$id
    SET
      $set
    WHERE
      $where_changes
  ";
}
