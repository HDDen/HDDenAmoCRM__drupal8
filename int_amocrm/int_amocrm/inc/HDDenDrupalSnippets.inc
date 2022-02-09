<?php
use Drupal\file\Entity\File;

class HDDenDrupalSnippets {
  public function getFileLink($id, $d7 = false){
    if (!$d7){
      // Load file
      $file = File::load($id);
      // Get URI.
      $file_uri = $file->getFileUri();
      $file_uri = file_create_url($file_uri);

      return $file_uri;
    } else {
      $file = file_load($id);
      $uri = $file->uri;
      $url = file_create_url($uri);

      return $url;
    }
  }
}
