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

  public function add_lead($lead_data) {
    include_once __DIR__ . '/bootstrap.php';

    // проверка доступности логгера
    if (class_exists(HDDenLogger::class)){
      $this->log = new HDDenLogger();
    } else {
      $this->log = false;
    }

    $name = $lead_data['NAME'];
    $phone = $lead_data['PHONE'];
    $email = $lead_data['EMAIL'];
    $description = $lead_data['TEXT'] ? $lead_data['TEXT'] : '';
    $leadName = $lead_data['LEAD_NAME'];
    $tag_lead_raw = $lead_data['TAG'] ? $lead_data['TAG'] : array();
    $tag_contact_raw = $lead_data['TAG_CONTACT'] ? $lead_data['TAG_CONTACT'] : array();
    $sitename = $lead_data['SITENAME'] ? $lead_data['SITENAME'] : $_SERVER['SERVER_NAME'];
    //$city = $lead_data['CITY'];
    $companyName = $lead_data['COMPANY'] ? $lead_data['COMPANY'] : '';
    $files = false;//$files = $lead_data['FILES'] ? $lead_data['FILES'] : '';
    $src = $lead_data['SRC'] ? $lead_data['SRC'] : '';
    $pipeline = $lead_data['pipelineId'] ? $lead_data['pipelineId'] : false;


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
    if (!empty($tag_contact_raw)){
      $tagsCollection_contact = new TagsCollection(); // пустая коллекция для хранения

      foreach ($tag_contact_raw as $contact_tag_value){ // перебор всех текстов для тегов
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
    if (!empty($tag_lead_raw)){
      $tagsCollection_lead = new TagsCollection(); // пустая коллекция для хранения

      foreach ($tag_lead_raw as $lead_tag_value){ // перебор всех текстов для тегов
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
      $query_str = $phone ? $phone : $email;
      $contacts = $apiClient->contacts()->get((new ContactsFilter())->setQuery($query_str));
      $contact = $contacts[0];
    } catch(AmoCRMApiException $e) {
      $contact = new ContactModel();
      $contact->setName($name);

      $CustomFieldsValues = new CustomFieldsValuesCollection();
      $emailField = (new MultitextCustomFieldValuesModel())->setFieldCode('EMAIL');
      $emailField->setValues((new MultitextCustomFieldValueCollection())->add((new MultitextCustomFieldValueModel())->setEnum('WORK')->setValue($email)));
      $phoneField = (new MultitextCustomFieldValuesModel())->setFieldCode('PHONE');
      $phoneField->setValues((new MultitextCustomFieldValueCollection())->add((new MultitextCustomFieldValueModel())->setEnum('WORK')->setValue($phone)));

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
    $lead->setName($leadName)->setContacts((new ContactsCollection())->add(($contact)));

    // Кастомные поля
    $this->log ? $this->log->write('Создаем поля') : false;
    //Создадим коллекцию полей сущности
    $CustomFieldsValues = new CustomFieldsValuesCollection();

    /* *
     * Адрес сайта
     * */
    if ($sitename){
      //Создадим модель значений поля типа текст
      $srcField_textCustomFieldValuesModel = new TextCustomFieldValuesModel();
      //Укажем ID поля
      $srcField_textCustomFieldValuesModel->setFieldId(413083);
      //Добавим значения
      $srcField_textCustomFieldValuesModel->setValues(
        (new TextCustomFieldValueCollection())
          ->add((new TextCustomFieldValueModel())->setValue($sitename))
      );
      //Добавим значение в коллекцию полей сущности
      $CustomFieldsValues->add($srcField_textCustomFieldValuesModel);
    }

    /* *
     * Страница обращения
     * */
    if ($src){
      //Создадим модель значений поля типа текст
      $srcField_textCustomFieldValuesModel = new TextCustomFieldValuesModel();
      //Укажем ID поля
      $srcField_textCustomFieldValuesModel->setFieldId(381167);
      //Добавим значения
      $srcField_textCustomFieldValuesModel->setValues(
        (new TextCustomFieldValueCollection())
          ->add((new TextCustomFieldValueModel())->setValue($src))
      );
      //Добавим значение в коллекцию полей сущности
      $CustomFieldsValues->add($srcField_textCustomFieldValuesModel);
    }

    /**
     * Прикреплён файл
     */
    /*if ($files){
      $filesField_textCustomFieldValuesModel = new TextCustomFieldValuesModel();
      $filesField_textCustomFieldValuesModel->setFieldId(976483); // ID поля
      $filesField_textCustomFieldValuesModel->setValues(
        (new TextCustomFieldValueCollection())
          ->add((new TextCustomFieldValueModel())->setValue($files))
      );
      $CustomFieldsValues->add($filesField_textCustomFieldValuesModel);
    }*/


    // Установим сущности ВСЕ собранные поля РАЗОМ
    $lead->setCustomFieldsValues($CustomFieldsValues);
    // С полями закончили


    // Назначим теги лиду
    $this->log ? $this->log->write('Назначаем теги лиду') : false;
    if (isset($tagsCollection_lead)) $lead->setTags($tagsCollection_lead);

    // Назначим воронку лиду
    if (isset($pipeline)){
      $this->log ? $this->log->write('Назначаем воронку лиду') : false;
      $lead->setPipelineId($pipeline);
    }

    // Сборка и отправка
    $leadsCollection = new LeadsCollection();
    $leadsCollection->add($lead);

    $this->log ? $this->log->write('Отправка лида в амо') : false;
    try {
      $leadsCollection = $leadsService->add($leadsCollection);
      $lead_id = $leadsCollection[0]->id;
      if($companyName != '') {
        //Создадим компанию
        $company = new CompanyModel();
        $company->setName($companyName);

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

      if($description != '') {
        $notesCollection = new NotesCollection();
        $serviceMessageNote = new CommonNote();
        $serviceMessageNote->setEntityId($lead_id)->setText($description);

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
