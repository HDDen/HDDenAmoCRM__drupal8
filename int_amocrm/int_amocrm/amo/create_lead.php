<?php

use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Collections\CompaniesCollection;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Collections\NullTagsCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Filters\LeadsFilter;
use AmoCRM\Models\CompanyModel;
use AmoCRM\Models\ContactModel;
use AmoCRM\Collections\NotesCollection;
use AmoCRM\Models\NoteType\CommonNote;
use AmoCRM\Models\NoteType\ServiceMessageNote;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NullCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use League\OAuth2\Client\Token\AccessTokenInterface;
use AmoCRM\Models\CustomFieldsValues\SelectCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\SelectCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\SelectCustomFieldValueModel;

use AmoCRM\Filters\TagsFilter;
use AmoCRM\Collections\TagsCollection;
use AmoCRM\Models\TagModel;
use AmoCRM\Models\CustomFields\TextCustomFieldModel;

class amoCRM
{
  private $log;

  /**
   * Создает поле, назначает ему значение
   */
  private function createFieldValue($value, $type, $id){
    //Создадим модель значений поля нашего типа
    if ($type == 'text'){
      
      $сustomFieldValuesModel = new TextCustomFieldValuesModel();
      //Укажем ID поля
      $сustomFieldValuesModel->setFieldId($id);
      //Добавим значения
      $сustomFieldValuesModel->setValues(
        (new TextCustomFieldValueCollection())
          ->add((new TextCustomFieldValueModel())->setValue($value))
      );

    } else if ($type == 'select') {

      $сustomFieldValuesModel = new SelectCustomFieldValuesModel();
      //Укажем ID поля
      $сustomFieldValuesModel->setFieldId($id);
      //Добавим значения
      $сustomFieldValuesModel->setValues(
        (new SelectCustomFieldValueCollection())
            ->add((new SelectCustomFieldValueModel())->setValue($value)) //Текст должен совпадать с одним из значений поля статус
      );

    } else {
      $this->log ? $this->log->write('createFieldValue(): не распознали тип поля или он не поддерживается, $type="'.$type.'"') : false;
      return false;
    }

    return $сustomFieldValuesModel;
  }

  public function add_lead($lead_data) {
    include_once __DIR__ . '/bootstrap.php';

    // проверка доступности логгера
    if (class_exists(HDDenLogger::class)){
      $this->log = new HDDenLogger();
    } else {
      $this->log = false;
    }

    // делаем extract
    $this->log ? $this->log->write('Распаковываем полученные переменные') : false;
    extract($lead_data, EXTR_PREFIX_ALL, 'importedLeadData');

    // Первый шаг - получаем токен. После авторизации можно делать остальное
    $this->log ? $this->log->write('Получаем токен') : false;
    $accessToken = getToken();

    $apiClient->setAccessToken($accessToken)
      ->setAccountBaseDomain($accessToken->getValues()['baseDomain'])
      ->onAccessTokenRefresh(
        function (AccessTokenInterface $accessToken, string $baseDomain) {
          saveToken([
            'accessToken' => $accessToken->getToken(),
            'refreshToken' => $accessToken->getRefreshToken(),
            'expires' => $accessToken->getExpires(),
            'baseDomain' => $baseDomain,
          ]);
        }
      );

    // Поиск или установка тега (для контакта)
    $this->log ? $this->log->write('Работа с тегами контакта') : false;
    if (isset($importedLeadData_tag_contact_raw) && !empty($importedLeadData_tag_contact_raw)){
      $tagsCollection_contact = new TagsCollection(); // пустая коллекция для хранения

      foreach ($importedLeadData_tag_contact_raw as $contact_tag_value){ // перебор всех текстов для тегов
        $contact_tag_value = $contact_tag_value;

        $tagsFilter = new TagsFilter();
        $tagsFilter->setQuery($contact_tag_value);
        try {

          // Запрашиваем теги контактов по нашему запросу
          $tag_data = $apiClient->tags(EntityTypesInterface::CONTACTS)->get($tagsFilter);

          // берем первый тег из результата. По идее, он должен быть единственным
          $tag = $tag_data->first();

        } catch (AmoCRMApiException $e) {

          // Тег не существует. Создаём
          $this->log ? $this->log->write('Создание тега') : false;
          $tag = new TagModel();
          $tag->setName($contact_tag_value);

          $tagsService = $apiClient->tags(EntityTypesInterface::CONTACTS);

          try {
            $tagsService->add((new TagsCollection())->add($tag)); // Не уверен, но по идее отправляем в амо
          } catch (AmoCRMApiException $e) {
            // printError($e);

            $logdata = 'Ошибка: '.$e->getTitle().', код: '.$e->getCode().', текст ошибки:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
            $this->log ? $this->log->write($logdata) : false;

            die;
          }

        }

        // Добавляем получившийся тег в коллекцию
        $tagsCollection_contact->add($tag);
      }
      unset($tag);
    }

    // Поиск или установка тега (для сделки).
    // По сути - дубликат кода выше, с минимальным изменением названия переменных. Отрефакторить
    $this->log ? $this->log->write('Работа с тегами сделки') : false;
    if (isset($importedLeadData_tag_lead_raw) && !empty($importedLeadData_tag_lead_raw)){
      $tagsCollection_lead = new TagsCollection(); // пустая коллекция для хранения

      foreach ($importedLeadData_tag_lead_raw as $lead_tag_value){ // перебор всех текстов для тегов
        $lead_tag_value = $lead_tag_value;

        $tagsFilter = new TagsFilter();
        $tagsFilter->setQuery($lead_tag_value);
        try {

          // Запрашиваем теги контактов по нашему запросу
          $tag_data = $apiClient->tags(EntityTypesInterface::LEADS)->get($tagsFilter);

          // берем первый тег из результата. По идее, он должен быть единственным
          $tag = $tag_data->first();

        } catch (AmoCRMApiException $e) {

          // Тег не существует. Создаём
          $this->log ? $this->log->write('Создаем тег сделки') : false;
          $tag = new TagModel();
          $tag->setName($lead_tag_value);

          $tagsService = $apiClient->tags(EntityTypesInterface::LEADS);

          try {
            $tagsService->add((new TagsCollection())->add($tag)); // Не уверен, но по идее отправляем в амо
          } catch (AmoCRMApiException $e) {
            //printError($e);

            $logdata = 'Ошибка: '.$e->getTitle().', код: '.$e->getCode().', текст ошибки:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
            $this->log ? $this->log->write($logdata) : false;

            die;
          }

        }

        // Добавляем получившийся тег в коллекцию
        $tagsCollection_lead->add($tag);
      }
      unset($tag);
    }

    // Выгружаем в АМО
    $leadsService = $apiClient->leads();

    // Создаём контакт
    $this->log ? $this->log->write('Создаём контакт') : false;
    try {
      $query_str = $importedLeadData_phone ? $importedLeadData_phone : $importedLeadData_email;
      $contacts = $apiClient->contacts()->get((new ContactsFilter())->setQuery($query_str));
      $contact = $contacts[0];
    } catch(AmoCRMApiException $e) {
      $contact = new ContactModel();
      $contact->setName($importedLeadData_name);

      $CustomFieldsValues = new CustomFieldsValuesCollection();
      $emailField = (new MultitextCustomFieldValuesModel())->setFieldCode('EMAIL');
      $emailField->setValues((new MultitextCustomFieldValueCollection())->add((new MultitextCustomFieldValueModel())->setEnum('WORK')->setValue($importedLeadData_email)));
      $phoneField = (new MultitextCustomFieldValuesModel())->setFieldCode('PHONE');
      $phoneField->setValues((new MultitextCustomFieldValueCollection())->add((new MultitextCustomFieldValueModel())->setEnum('WORK')->setValue($importedLeadData_phone)));

      $CustomFieldsValues->add($emailField);
      $CustomFieldsValues->add($phoneField);

      $contact->setCustomFieldsValues($CustomFieldsValues);

      // Назначим теги контакту
      if (isset($tagsCollection_contact)) $contact->setTags($tagsCollection_contact);

      try {
        $contactModel = $apiClient->contacts()->addOne($contact);
      } catch (AmoCRMApiException $e) {
        // printError($e);

        $logdata = 'Ошибка: '.$e->getTitle().', код: '.$e->getCode().', текст ошибки:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
        $this->log ? $this->log->write($logdata) : false;

        die;
      }
    }

    // Создаем сделку
    $this->log ? $this->log->write('Создаём сделку') : false;
    $lead = new LeadModel();
    $lead->setName($importedLeadData_lead_name)->setContacts((new ContactsCollection())->add(($contact)));

    /**
     * Кастомные поля лида
     */
    $this->log ? $this->log->write('Создаем поля') : false;

    //Создадим коллекцию полей сущности
    $CustomFieldsValues = new CustomFieldsValuesCollection();
    
    // адрес сайта
    if (isset($importedLeadData_sitename) && $importedLeadData_sitename){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_sitename, 'text', 413083));
    }

    // Страница обращения
    if (isset($importedLeadData_src) && $importedLeadData_src){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_src, 'text', 381167));
    }

    // Прикреплён файл
    if (isset($importedLeadData_files) && $importedLeadData_files){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_files, 'text', 976483));
    }

    // Зарегистрироваться в клинику
    if (isset($importedLeadData_clinicToRegister) && $importedLeadData_clinicToRegister){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_clinicToRegister, 'text', 464085));
    }

    // Записаться к доктору
    if (isset($importedLeadData_docToRegister) && $importedLeadData_docToRegister){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_docToRegister, 'text', 464093));
    }

    // Время для звонка
    if (isset($importedLeadData_timeToCall) && $importedLeadData_timeToCall){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_timeToCall, 'text', 382275));
    }

    // Интересующая услуга
    if (isset($importedLeadData_interested) && $importedLeadData_interested){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_interested, 'text', 382217));
    }

    // Возраст
    if (isset($importedLeadData_age) && $importedLeadData_age){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_age, 'text', 381015));
    }
    // Пол
    if (isset($importedLeadData_sex) && $importedLeadData_sex){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_sex, 'text', 381017));
    }
    // Самостоятельность
    if (isset($importedLeadData_selfdependence) && $importedLeadData_selfdependence){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_selfdependence, 'text', 381021));
    }
    // Степерь псих. заболеваний
    if (isset($importedLeadData_psycho) && $importedLeadData_psycho){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_psycho, 'text', 381023));
    }
    // Желаемая дата въезда
    if (isset($importedLeadData_arrivaldate) && $importedLeadData_arrivaldate){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_arrivaldate, 'text', 381013));
    }
    // Желаемая дата въезда
    if (isset($importedLeadData_roomPlaces) && $importedLeadData_roomPlaces){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_roomPlaces, 'text', 381163));
    }

    // откуда
    if (isset($importedLeadData_travelFrom) && $importedLeadData_travelFrom){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_travelFrom, 'text', 382429));
    }
    // куда
    if (isset($importedLeadData_travelTo) && $importedLeadData_travelTo){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_travelTo, 'text', 382431));
    }
    // предв. расчет
    if (isset($importedLeadData_preEstimate) && $importedLeadData_preEstimate){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_preEstimate, 'text', 381139));
    }
    // ночной тариф 
    if (isset($importedLeadData_nightTariff) && $importedLeadData_nightTariff){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_nightTariff, 'text', 381141));
    }
    // поддержка санитаров
    if (isset($importedLeadData_sanitars) && $importedLeadData_sanitars){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_sanitars, 'select', 381161));
    }

    // дата для экскурсии
    if (isset($importedLeadData_excursion) && $importedLeadData_excursion){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_excursion, 'text', 381075));
    }


    // Теперь линкуем сущности все собранные поля
    $lead->setCustomFieldsValues($CustomFieldsValues);
    /**
     * С полями закончили
     */


    // Назначим теги лиду
    $this->log ? $this->log->write('Назначаем теги лиду') : false;
    if (isset($tagsCollection_lead)) $lead->setTags($tagsCollection_lead);

    // Назначим воронку лиду
    if (isset($importedLeadData_pipelineId) && $importedLeadData_pipelineId){
      $this->log ? $this->log->write('Назначаем воронку лиду') : false;
      $lead->setPipelineId($importedLeadData_pipelineId);
    }

    // Сборка и отправка
    $leadsCollection = new LeadsCollection();
    $leadsCollection->add($lead);

    $this->log ? $this->log->write('Отправка лида в амо') : false;
    try {
      $leadsCollection = $leadsService->add($leadsCollection);
      $lead_id = $leadsCollection[0]->id;
      if(isset($importedLeadData_companyName) && $importedLeadData_companyName) {
        //Создадим компанию
        $company = new CompanyModel();
        $company->setName($importedLeadData_companyName);

        $companiesCollection = new CompaniesCollection();
        $companiesCollection->add($company);
        try {
          $apiClient->companies()->add($companiesCollection);
        } catch (AmoCRMApiException $e) {
          //printError($e);

          $logdata = 'Ошибка: '.$e->getTitle().', код: '.$e->getCode().', текст ошибки:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
          $this->log ? $this->log->write($logdata) : false;

          die;
        }

        $links = new LinksCollection();
        $links->add($contact);
        try {
          $apiClient->companies()->link($company, $links);
        } catch (AmoCRMApiException $e) {
          //printError($e);

          $logdata = 'Ошибка: '.$e->getTitle().', код: '.$e->getCode().', текст ошибки:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
          $this->log ? $this->log->write($logdata) : false;

          die;
        }
      }

      if(isset($importedLeadData_message) && $importedLeadData_message) {
        $notesCollection = new NotesCollection();
        $serviceMessageNote = new CommonNote();
        $serviceMessageNote->setEntityId($lead_id)->setText($importedLeadData_message);

        $notesCollection->add($serviceMessageNote);

        try {
          $leadNotesService = $apiClient->notes(EntityTypesInterface::LEADS);
          $notesCollection = $leadNotesService->add($notesCollection);
        } catch (AmoCRMApiException $e) {
          //printError($e);

          $logdata = 'Ошибка: '.$e->getTitle().', код: '.$e->getCode().', текст ошибки:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
          $this->log ? $this->log->write($logdata) : false;

          die;
        }
      }
      $this->log ? $this->log->write('Success! Отправлено.') : false;
      return $lead_id;
    } catch (AmoCRMApiException $e) {
      // printError($e);

      $logdata = 'Ошибка: '.$e->getTitle().', код: '.$e->getCode().', текст ошибки:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
      $this->log ? $this->log->write($logdata) : false;

      die;
    }

  }
}
