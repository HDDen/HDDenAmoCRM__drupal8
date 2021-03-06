<?php
/*
Plugin Name: Custom AmoCRM integration
Description: Интеграция сайта с AmoCRM, используя OAuth
Version: 2.0.1
Author: HDDen (madmen.bz)
Requires PHP: 7.4
*/

require_once 'inc/HDDenAmoUtils.inc';
require_once 'inc/HDDenLogger.inc';
require_once 'inc/HDDenDrupalSnippets.inc';
require_once 'amo/create_lead.php';

// Само получение данных
add_action( 'wpcf7_before_send_mail', 'cf7_AmoCRM_Integration', 10, 3 );
function cf7_AmoCRM_Integration($cf7, &$abort, $object){

  // демо-режим
  $demo = false;

  // проверка доступности логгера
  if (class_exists(HDDenLogger::class)){
      $log = new HDDenLogger();
  } else {
      $log = false;
  }

  // установка временной зоны
  $oldTimezone = date_default_timezone_get();
  date_default_timezone_set('Europe/Moscow');

  // creating amoutils instance
  $amoUtils = new HDDenAmoUtils();

  // Получаем значения
  $submission = WPCF7_Submission::get_instance();
  $values = false;
  if ($submission){
    $values = $submission->get_posted_data();
  }

  // Фиксируем id
  $form_id = strval($cf7->id);

  // можем выбрать воронку
  $pipelines = [
    //'site.ru' => 123, // integer
    //'default' => 5240941 // не обязательно, если у вас одна-единственная воронка
  ];

  // Нужно сопоставить поля в $values полям в передаваемом массиве.
  $formsArray = array(
    // 'id' => array('field' => 'pseudoname'),
    //'message' => '', или если из нескольких полей, то массив
    //'message' => array('field1', 'field2', ..., 'field_n'),
    '828' => [
        'name' => 'your-name',
        'phone' => 'your-phone',
        'timeToCall' => 'your-time',
        'tags' => ['lead'=>['site.ru'], 'contact'=>['Контакт из site.ru']],
        'formName' => 'Основная форма',
    ],
  );

  $blocked = array('9999'); // отсюда не будет ничего приходить

  // переназначаем маппинг для поддоменов. домен -> [id формы на нём = id рамеченной формы]
  $subdomains = [
    'anapa.grail.su' => ['189' => '312', '190' => '311'],
    'armavir.grail.su' => ['186' => '312', '187' => '311'],
    'belorechensk.grail.su' => ['186' => '312', '187' => '311'],
    'gelendzhik.grail.su' => ['186' => '312', '187' => '311'],
    'goryachii-kluch.grail.su' => ['185' => '312', '186' => '311' ],
    'ejsk.grail.su' => ['186' => '312', '187' => '311'],
    'korenovsk.grail.su' => ['185' => '312', '186' => '311'],
    'kropotkin.grail.su' => ['185' => '312', '186' => '311'],
    'krymsk.grail.su' => ['185' => '312', '186' => '311'],
    'kurganinsk.grail.su' => ['185' => '312', '186' => '311'],
    'labinsk.grail.su' => ['185' => '312', '186' => '311'],
    'majkop.grail.su' => ['185' => '312', '186' => '311'],
    'novorossijsk.grail.su' => ['195' => '312', '196' => '311'],
    'slavyansk-na-kubani.grail.su' => ['185' => '312', '186' => '311'],
    'sochi.grail.su' => ['187' => '312', '188' => '311'],
    'temruk.grail.su' => ['185' => '312', '186' => '311'],
    'timashevsk.grail.su' => ['185' => '312', '186' => '311'],
    'tihoreck.grail.su' => ['185' => '312', '186' => '311'],
    'tuapse.grail.su' => ['186' => '312', '187' => '311'],
  ];
  
  $temp_serverName = strtok($_SERVER['SERVER_NAME'], ':');
  if (array_key_exists($temp_serverName, $subdomains) && !empty($subdomains[$temp_serverName]) && array_key_exists($form_id, $subdomains[$temp_serverName])){
    $log ? $log->write('Main: проверка переназначения id формы: SERVER_NAME = '.$temp_serverName.' - успех, '.$form_id.' => '.$subdomains[$temp_serverName]) : null ;
    $form_id = $subdomains[$temp_serverName][$form_id];
  } else {
    $log ? $log->write('Main: проверка переназначения id формы: SERVER_NAME = '.$temp_serverName.' - неудача, используем оригинальный маппинг') : null ;
  }
  unset($temp_serverName);
  
  // проверяем, не заблокирована ли форма
  if (in_array($form_id, $blocked)){
    $log ? $log->write('Main: обращение из заблокированной формы id'.$form_id.': '.print_r($values, true)) : null ;
    return true;
  } else {
    $log ? $log->write('Main: получено обращение: '.$form_id.PHP_EOL.'var_export($values, true)') : null ;
  }

  // Проверяем, размечена ли полученная форма
  if (array_key_exists($form_id, $formsArray) && !empty($values)) {

    // сразу пропишем страницу. Если что, переназначим её
    //$amoUtils->setField('src', $_SERVER['HTTP_REFERER']);

    // обходим только размеченные поля
    foreach ($formsArray[$form_id] as $amoFieldName => $formFieldName) {

      // проверка на массив тегов
      if ($amoFieldName == 'tags') {
          // теги сделки
          if (isset($formFieldName['lead'])) {
              $amoUtils->addLeadTags($formFieldName['lead']);
          }
          // теги контакта
          if (isset($formFieldName['contact'])) {
              $amoUtils->addLeadTags($formFieldName['contact']);
          }
          // переходим к следующему полю
          continue;
      }

      // поддержка составных полей
      if (is_array($formFieldName)) {
        // запускаем цикл по компонентам составного поля - обходим каждое перечисленное
        foreach ($formFieldName as $part_name) {
          if (array_key_exists($part_name, $values) || isset($values[$part_name])) { // второе - для поддержки drupal 7, где доступ по порядковому номеру а не по ключу
            // Значение может быть как в виде строки, так и массивом.

            // В drupal 7 если мы передавали файл, мы никак его не вычислим, исходя только из значения поля.
            // Но в $submission передастся массив ['file_usage']['added_fids'][...], в котором перечислены id добавленных файлов.
            // если значение поля внезапно есть в этом массиве - был передан id файла, и нужно попытаться получить ссылку на него.
            $drupal7_tryfield = false; // дефолт
            if (isset($submission) && isset($submission->file_usage) && isset($submission->file_usage['added_fids']) && !empty($submission->file_usage['added_fids'])){
              if (is_array($values[$part_name])
                  && (count($values[$part_name]) === 1)
                  && in_array($values[$part_name][0], $submission->file_usage['added_fids']))
              {
                $drupal7_tryfield = 'd7_file';
              }
            }

            $val = $amoUtils->parseValue($formFieldName, $values[$part_name], $drupal7_tryfield);
            $amoUtils->setField($amoFieldName, $val, true);
          }
        }
      } else {
        if (array_key_exists($formFieldName, $values)) { // второе - для поддержки drupal 7, где доступ по порядковому номеру а не по ключу
          // Значение может быть как в виде строки, так и массивом.

          // скопированный выше способ детекта загрузки файла для Drupal 7
          $drupal7_tryfield = false; // дефолт
          if (isset($submission) && isset($submission->file_usage) && isset($submission->file_usage['added_fids']) && !empty($submission->file_usage['added_fids'])){
            if (is_array($values[$formFieldName])
              && (count($values[$formFieldName]) === 1)
              && in_array($values[$formFieldName][0], $submission->file_usage['added_fids']))
            {
              $drupal7_tryfield = 'd7_file';
            }
          }

          $val = $amoUtils->parseValue($formFieldName, $values[$formFieldName], $drupal7_tryfield);
          $amoUtils->setField($amoFieldName, $val);
        }
      }
    }

    // Допишем в $message имя формы
    if (isset($formsArray[$form_id]['formName'])) {
      $amoUtils->setMessage('Форма: ' . $formsArray[$form_id]['formName'], true);
    }
  } else if (!empty($values)) {
    // форма не распознана, соберем всё в $message
    $log ? $log->write('Main: форма не опознана, полученные значения: ' . PHP_EOL . print_r($values, true)) : null;

    $message = 'Форма не распознана, полученные значения:' . PHP_EOL . PHP_EOL;
    foreach ($values as $values_index => $values_data) {
      $message .= "['$values_index'] = ".$amoUtils->parseValue(false, $values_data).PHP_EOL;
    }
    $message .= 'Форма "' . $form_id . '"' . PHP_EOL;

    // устанавливаем поле
    $amoUtils->setMessage($message);

    // пытаемся найти телефон
    if (isset($values['field_phone'])) {
      $phone = $amoUtils->parseValue(false, $values['field_phone']);
    } else if (isset($values['phone'])) {
      $phone = $amoUtils->parseValue(false, $values['phone']);
    }
    if ($phone) {
      $amoUtils->setPhone($phone);
    } else {
      $amoUtils->setEmail(time() . '@' . $_SERVER['SERVER_NAME']);
    }
  }

  // если нужно, устанавливаем имя сайта (например, кириллица не переводится)
  //$amoUtils->setField('sitename', 'lepart.su');

  // продолжение выбора воронки
  if (array_key_exists($_SERVER['SERVER_NAME'], $pipelines)) {
      $log ? $log->write('Main: устаналиваем воронку от ' . $_SERVER['SERVER_NAME'] . ' с id: ' . $pipelines['default']) : null;
      $amoUtils->setPipelineId($pipelines[$_SERVER['SERVER_NAME']]);
  } else if (isset($pipelines['default'])) {
      $log ? $log->write('Main: устаналиваем дефолтную воронку с id: ' . $pipelines['default']) : null;
      $amoUtils->setPipelineId($pipelines['default']);
  }

  // корректировка полей
  if (($form_id == 999999)) {

    // санитары - селект-поле, нужо прокидывать значение то которое заведено в срм
    if ($amoUtils->getField('sanitars')) {
      $amoUtils->setField('sanitars', 'Да');
    } else {
      $amoUtils->setField('sanitars', 'Нет');
    }

    // адреса наших пансионатов
    $amoUtils->setField('travelTo', str_replace(['hutor', 'srednyaya'], ['г. Краснодар, ул. Правобережная, д. 8', 'г. Краснодар, ул. Средняя, д. 43/5'], $amoUtils->getField('travelTo')));
  }
    
  // возраст зависимого
  if ($amoUtils->getField('age')){
    $old_arr = ['a1', 'a2', 'a3', 'a4', 'a5'];
    $replace_arr = [
      'от 14 до 18 лет',
      'от 18 до 21 года',
      'с 21 года до 30 лет',
      'с 30 до 40 лет',
      'выше 40 лет',
    ];
    $amoUtils->setField('age', str_replace($old_arr, $replace_arr, $amoUtils->getField('age')));
  }

  // здесь отправка
  if (!$demo) {
    $result = $amoUtils->send();
    $log ? $log->write('Main: результат отправки: ' . var_export($result, true)) : null;
  } else {
    $log ? $log->write('Main: установлен демо-режим, дошли до отправки но не стали') : null;
  }

  // возвращаем временную зону
  $oldTimezone = date_default_timezone_get();
  date_default_timezone_set($oldTimezone);

  // возвращаем ответ
  if ($result === false){
    $abort = true;
    $abort_msg = "Ошибка при отправке - пожалуйста, свяжитесь по телефону, либо в чате на сайте!".PHP_EOL.'(amo)'; //<- your custom error message
  	//$msgs = $cf7->prop('messages');
    //$msgs['mail_sent_ng'] = $abort_msg; //<- your custom error message
  	//$cf7->set_properties(array('messages' => $msgs));
    $object->set_response($abort_msg);
  	return false;
  } else {
    return $result;
  }
}
