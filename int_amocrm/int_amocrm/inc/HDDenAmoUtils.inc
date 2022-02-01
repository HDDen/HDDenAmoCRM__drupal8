<?php
// объявили класс

class HDDenAmoUtils {

  public $formData; // создаём публичное поле с данными из формы. Паблик - чтобы можно было считать из класса как свойство
  private $sitename;
  private $datetime;
  private $log;

  public function __construct($formData = false) {
    // проверка доступности логгера
    if (class_exists(HDDenLogger::class)){
      $this->log = new HDDenLogger();
    } else {
      $this->log = false;
    }

    // установим время и дату
    $this->datetime = date("d/m/Y H:i:s");

    // установим sitename
    $this->sitename = $_SERVER['SERVER_NAME'];

    // установим $formData, если он был получен
    if ($formData){
      $this->init($formData);
    }
  }

  public function init($formData){
    // Здесь проверим коррэктность массива
    if (is_array($formData) && !empty($formData)){
      $this->formData = $formData;

      // установим sitename
      if (@$this->formData['sitename']){
        $this->sitename = $this->formData['sitename'];
      } else {
        $this->sitename = $_SERVER['SERVER_NAME'];
      }

      // проверяем существование имени
      if (!(@$this->formData['name'])){
        $this->formData['name'] = $this->generateName(false);
      }
      $this->formData['name_uniqueized'] = $this->addMetainfToName($this->formData['name']);

    } else {
      return false; // вообще возвращать бы ошибку
    }
  }

  /**
   * Устанавливает значение $formData['name']
   * @param $name
   */
  public function setName($name){
    if (is_string($name) && $name){
      $this->formData['name'] = $name;
    }
  }

  /**
   * Устанавливает значение $formData['phone']
   * @param $phone
   */
  public function setPhone($phone){
    if (is_string($phone) && $phone){
      $this->formData['phone'] = $phone;
    }
  }

  /**
   * Устанавливает значение $formData['email']
   * @param $email
   */
  public function setEmail($email){
    if (is_string($email) && $email){
      $this->formData['email'] = $email;
    }
  }

  /**
   * Устанавливает значение $formData['message']
   * @param $message
   * @param bool $append
   */
  public function setMessage($message, $append = false){
    if (is_string($message) && $message){
      if (!$append){
        $this->formData['message'] = $message;
      } else {

        if (!(@$this->formData['message'])){
          $this->formData['message'] = '';
        }

        $this->formData['message'] .= PHP_EOL . $message;
      }

      return true;

    } else {
      return false;
    }
  }

  /**
   * Устанавливает значение массива $formData
   * @param $fieldName
   * @param $value
   */
  public function setField($fieldName, $value, $append = false){
    if ($append){
      if (isset($this->formData[$fieldName])){
        $old = $this->formData[$fieldName];
      } else {
        $old = '';
      }

      if ($old){
        $old .= '; '.PHP_EOL;
      }
      $old .= $value;
      $this->formData[$fieldName] = $old;

    } else {
      $this->formData[$fieldName] = $value;
    }
  }


  /**
   * Добавит сайт и метку времени к имени
   *
   * @param $name
   *
   * @return string
   */
  private function addMetainfToName($name){
    return $name . ', ' . $this->sitename.' в '.$this->datetime;
  }

  /**
   * Принудительная генерация имени из сопутствующих данных
   * @param bool $addMeta
   * @return string
   */
  private function generateName($addMeta = false){
    $newName = '';

    if ($this->formData['phone']){
      $newName = $this->formData['phone'];
    } else if ($this->formData['email']){
      $newName = $this->formData['email'];
    } else {
      $newName = 'Без имени';
    }

    // прогоняет имя через добавление доп. данных, при водя к "Иван, site.com в 31/12/2022 00:00:00"
    if ($addMeta){
      $newName = $this->addMetainfToName($newName);
    }

    return $newName;
  }

  /**
   * Преобразовывает переменную в строку
   * @param $data
   *
   * @return string
   */
  public function parseValue($data){
    $result = '';

    // если был передан массив, собираем все значения в строку
    if (is_array($data)){

      if (!empty($data)){

        // Проверка на друпаловский массив с одним эллементом типа
        if ( (count($data) === 1) ){

          // $data[0]['value'] = '...';
          if (isset($data[0]['value'])){
            $result .= $data[0]['value'];
          } else if ( isset($data[0]['fids']) && !empty($data[0]['fids']) ){ // файлы

            // если подключен класс со сниппетами к друпалу
            if (class_exists(HDDenDrupalSnippets::class)){
              foreach ($data[0]['fids'] as $index => $fid){
                if ($result){
                  $result .= ' ; ';
                }
                $file_uri = \HDDenDrupalSnippets::getFileLink($fid);
                $result .= $file_uri;
              }
            }
          }
        } else if (!isAssoc){
          foreach ($data as $data_elem){
            // продолжаем строку
            if ($result !== ''){
              $result .= ';' . PHP_EOL;
            }

            if (is_array($data_elem)){
              $result .= print_r($data_elem, true);
            } else {
              $result .= $data_elem;
            }
          }
        } else {
          foreach ($data as $data_name => $data_contain){
            // продолжаем строку
            if ($result !== ''){
              $result .= ';' . PHP_EOL;
            }

            $result .= $data_name . ': ';

            if (is_array($data_contain)){
              $result .= print_r($data_contain, true);
            } else {
              $result .= $data_contain;
            }
          }
        }
      }

    } else {
      $result = $data;
    }

    return $result;
  }

  /**
   * Проверяет, передан ассоциативный массив или по индексам
   * @param array $arr
   *
   * @return bool
   */
  private function isAssoc(array $arr){
    if (array() === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
  }

  public function send(){
    if ($this->log){
      $this->log->write($this->formData);
    }

    // проведем init еще раз
    $this->init($this->formData);

    // проверка
    if (empty($this->formData)){
      $this->log ? $this->log->write('Нечего выгружать') : false;
      return false;
    }

    // Генерим имя сделки
    $leadName = $this->formData['name_uniqueized'];

    // Теги
    $leadTag = array( ''.$this->sitename );
    $contactTag = array( 'Контакт из '.$this->sitename );

    // Собираем массив

    // Пост-проверка данных
    if (!$this->formData['phone'] && !$this->formData['email']){
      $this->log ? $this->log->write('Не найдено ни $phone, ни $email. Выход') : false;
      return false;
    }

    // массив с 'NAME', 'PHONE', 'EMAIL', 'TEXT' - описание, 'LEAD_NAME' - название сделки, 'TAG' - массив, 'TAG_CONTACT' - массив но для контакта
    $amo_array = array(
      'NAME' => $this->formData['name_uniqueized'], // имя контакта
      'PHONE' => @$this->formData['phone'],
      'EMAIL' => @$this->formData['email'],
      'TEXT' => @$this->formData['message'],
      'FILES' => @$this->formData['files'],
      'LEAD_NAME' => $leadName, // название сделки
      'TAG' => $leadTag,
      'TAG_CONTACT' => $contactTag,
    );

    // Отправляем
    $amoCrm = new AmoCRM();
    $result = $amoCrm->add_lead($amo_array);

    $this->log ? $this->log->write('Результат: '.PHP_EOL.var_export($result, true)) : false;
    return true;
  }
}
