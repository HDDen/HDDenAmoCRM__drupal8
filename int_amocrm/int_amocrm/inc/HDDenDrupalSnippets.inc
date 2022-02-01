<?php
use Drupal\file\Entity\File;

class HDDenDrupalSnippets {
  public function getFileLink($id){
    // Load file
    $file = File::load($id);
    // Get URI.
    $file_uri = $file->getFileUri();
    $file_uri = file_create_url($file_uri);

    return $file_uri;
  }
}
