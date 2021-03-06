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
use AmoCRM\Models\CustomFieldsValues\CheckboxCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\CheckboxCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\CheckboxCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\MultiselectCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultiselectCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultiselectCustomFieldValueModel;

use AmoCRM\Filters\TagsFilter;
use AmoCRM\Collections\TagsCollection;
use AmoCRM\Models\TagModel;
use AmoCRM\Models\CustomFields\TextCustomFieldModel;

class amoCRM
{
  private $log;

  /**
   * ?????????????? ????????, ?????????????????? ?????? ????????????????
   */
  private function createFieldValue($value, $type, $id){
    //???????????????? ???????????? ???????????????? ???????? ???????????? ????????
    if ($type == 'text'){

      $??ustomFieldValuesModel = new TextCustomFieldValuesModel();
      //???????????? ID ????????
      $??ustomFieldValuesModel->setFieldId($id);
      //?????????????? ????????????????
      $??ustomFieldValuesModel->setValues(
        (new TextCustomFieldValueCollection())
          ->add((new TextCustomFieldValueModel())->setValue($value))
      );

    } else if ($type == 'select') {

      $??ustomFieldValuesModel = new SelectCustomFieldValuesModel();
      //???????????? ID ????????
      $??ustomFieldValuesModel->setFieldId($id);
      //?????????????? ????????????????
      $??ustomFieldValuesModel->setValues(
        (new SelectCustomFieldValueCollection())
            ->add((new SelectCustomFieldValueModel())->setValue($value))
      );

    } else if ($type == 'checkbox') {
      
      $??ustomFieldValuesModel = new CheckboxCustomFieldValuesModel();
      $??ustomFieldValuesModel->setFieldId($id);
      $??ustomFieldValuesModel->setValues(
        (new CheckboxCustomFieldValueCollection())
            ->add((new CheckboxCustomFieldValueModel())->setValue($value)) //?????????? ???????????? ?????????????????? ?? ?????????? ???? ???????????????? ???????? ????????????
      );

    } else if ($type == 'multiselect') {

      $??ustomFieldValuesModel = new MultiselectCustomFieldValuesModel();
      $??ustomFieldValuesModel->setFieldId($id);
      $??ustomFieldValuesModel->setValues(
        (new MultiselectCustomFieldValueCollection())
            ->add((new MultiselectCustomFieldValueModel())->setValue($value)) //?????????? ???????????? ?????????????????? ?? ?????????? ???? ???????????????? ???????? ????????????
      );

    } else {
      $this->log ? $this->log->write('createFieldValue(): ???? ???????????????????? ?????? ???????? ?????? ???? ???? ????????????????????????????, $type="'.$type.'"') : false;
      return false;
    }

    return $??ustomFieldValuesModel;
  }

  public function add_lead($lead_data) {
    $bootstrap_path = __DIR__ . '/bootstrap.php';
    require_once $bootstrap_path;

    // ???????????????? ?????????????????????? ??????????????
    if (class_exists(HDDenLogger::class)){
      $this->log = new HDDenLogger();
    } else {
      $this->log = false;
    }

    // ???????????? extract
    $this->log ? $this->log->write('?????????????????????????? ???????????????????? ????????????????????') : false;
    extract($lead_data, EXTR_PREFIX_ALL, 'importedLeadData');

    // ???????????? ?????? - ???????????????? ??????????. ?????????? ?????????????????????? ?????????? ???????????? ??????????????????
    $this->log ? $this->log->write('???????????????? ??????????') : false;
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

    // ?????????? ?????? ?????????????????? ???????? (?????? ????????????????)
    $this->log ? $this->log->write('???????????? ?? ???????????? ????????????????') : false;
    if (isset($importedLeadData_tag_contact_raw) && !empty($importedLeadData_tag_contact_raw)){
      $tagsCollection_contact = new TagsCollection(); // ???????????? ?????????????????? ?????? ????????????????

      foreach ($importedLeadData_tag_contact_raw as $contact_tag_value){ // ?????????????? ???????? ?????????????? ?????? ??????????
        $contact_tag_value = $contact_tag_value;

        $tagsFilter = new TagsFilter();
        $tagsFilter->setQuery($contact_tag_value);
        try {

          // ?????????????????????? ???????? ?????????????????? ???? ???????????? ??????????????
          $tag_data = $apiClient->tags(EntityTypesInterface::CONTACTS)->get($tagsFilter);

          // ???????? ??????
          $tag = $tag_data->getBy('name', $contact_tag_value);

          if(!$tag){
          	throw new AmoCRMApiException("Tag isn't exists");
          }

        } catch (AmoCRMApiException $e) {

          // ?????? ???? ????????????????????. ??????????????
          $this->log ? $this->log->write('???????????????? ????????') : false;
          $tag = new TagModel();
          $tag->setName($contact_tag_value);

          $tagsService = $apiClient->tags(EntityTypesInterface::CONTACTS);

          try {
            $tagsService->add((new TagsCollection())->add($tag)); // ???? ????????????, ???? ???? ???????? ???????????????????? ?? ??????
          } catch (AmoCRMApiException $e) {
            // printError($e);

            $logdata = '????????????: '.$e->getTitle().', ??????: '.$e->getCode().', ?????????? ????????????:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
            $this->log ? $this->log->write($logdata) : false;

	    // trigger error
            trigger_error($logdata, E_USER_WARNING);

	    return false; //die;
          }

        }

        // ?????????????????? ???????????????????????? ?????? ?? ??????????????????
        $tagsCollection_contact->add($tag);
      }
      unset($tag);
    }

    // ?????????? ?????? ?????????????????? ???????? (?????? ????????????).
    // ???? ???????? - ???????????????? ???????? ????????, ?? ?????????????????????? ???????????????????? ???????????????? ????????????????????. ??????????????????????????
    $this->log ? $this->log->write('???????????? ?? ???????????? ????????????') : false;
    if (isset($importedLeadData_tag_lead_raw) && !empty($importedLeadData_tag_lead_raw)){
      $tagsCollection_lead = new TagsCollection(); // ???????????? ?????????????????? ?????? ????????????????

      foreach ($importedLeadData_tag_lead_raw as $lead_tag_value){ // ?????????????? ???????? ?????????????? ?????? ??????????
        $lead_tag_value = $lead_tag_value;

        $tagsFilter = new TagsFilter();
        $tagsFilter->setQuery($lead_tag_value);
        try {

          // ?????????????????????? ???????? ?????????????????? ???? ???????????? ??????????????
          $tag_data = $apiClient->tags(EntityTypesInterface::LEADS)->get($tagsFilter);

          // ???????? ??????
          $tag = $tag_data->getBy('name', $lead_tag_value);

          if(!$tag){
	          throw new AmoCRMApiException("Tag isn't exists");
          }

        } catch (AmoCRMApiException $e) {

          // ?????? ???? ????????????????????. ??????????????
          $this->log ? $this->log->write('?????????????? ?????? ????????????') : false;
          $tag = new TagModel();
          $tag->setName($lead_tag_value);

          $tagsService = $apiClient->tags(EntityTypesInterface::LEADS);

          try {
            $tagsService->add((new TagsCollection())->add($tag)); // ???? ????????????, ???? ???? ???????? ???????????????????? ?? ??????
          } catch (AmoCRMApiException $e) {
            //printError($e);

            $logdata = '????????????: '.$e->getTitle().', ??????: '.$e->getCode().', ?????????? ????????????:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
            $this->log ? $this->log->write($logdata) : false;

            // trigger error
            trigger_error($logdata, E_USER_WARNING);

	    return false; //die;
          }

        }

        // ?????????????????? ???????????????????????? ?????? ?? ??????????????????
        $tagsCollection_lead->add($tag);
      }
      unset($tag);
    }

    // ?????????????????? ?? ??????
    $leadsService = $apiClient->leads();

    // ?????????????? ??????????????
    $this->log ? $this->log->write('?????????????? ??????????????') : false;
    try {
      $query_str = $importedLeadData_phone ? $importedLeadData_phone : $importedLeadData_email;
      $contacts = $apiClient->contacts()->get((new ContactsFilter())->setQuery($query_str));
      $contact = $contacts[0];
    } catch(AmoCRMApiException $e) {
      $contact = new ContactModel();
      $contact->setName($importedLeadData_name);

      $CustomFieldsValues = new CustomFieldsValuesCollection();
      if (isset($importedLeadData_email) && $importedLeadData_email){
        $emailField = (new MultitextCustomFieldValuesModel())->setFieldCode('EMAIL');
        $emailField->setValues((new MultitextCustomFieldValueCollection())->add((new MultitextCustomFieldValueModel())->setEnum('WORK')->setValue($importedLeadData_email)));
        $CustomFieldsValues->add($emailField);
      }

      if (isset($importedLeadData_phone) && $importedLeadData_phone){
        $phoneField = (new MultitextCustomFieldValuesModel())->setFieldCode('PHONE');
        $phoneField->setValues((new MultitextCustomFieldValueCollection())->add((new MultitextCustomFieldValueModel())->setEnum('WORK')->setValue($importedLeadData_phone)));
        $CustomFieldsValues->add($phoneField);
      }

      if ( (isset($importedLeadData_email) && $importedLeadData_email) || (isset($importedLeadData_phone) && $importedLeadData_phone) ){
        $contact->setCustomFieldsValues($CustomFieldsValues);
      }

      // ???????????????? ???????? ????????????????
      if (isset($tagsCollection_contact)) $contact->setTags($tagsCollection_contact);

      try {
        $contactModel = $apiClient->contacts()->addOne($contact);
      } catch (AmoCRMApiException $e) {
        // printError($e);

        $logdata = '????????????: '.$e->getTitle().', ??????: '.$e->getCode().', ?????????? ????????????:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
        $this->log ? $this->log->write($logdata) : false;

        // trigger error
	trigger_error($logdata, E_USER_WARNING);

	return false; //die;
      }
    }

    // ?????????????? ????????????
    $this->log ? $this->log->write('?????????????? ????????????') : false;
    $lead = new LeadModel();
    $lead->setName($importedLeadData_lead_name)->setContacts((new ContactsCollection())->add(($contact)));

    /**
     * ?????????????????? ???????? ????????
     */
    $this->log ? $this->log->write('?????????????? ????????') : false;

    //???????????????? ?????????????????? ?????????? ????????????????
    $CustomFieldsValues = new CustomFieldsValuesCollection();

    // ?????????? ??????????
    if (isset($importedLeadData_sitename) && $importedLeadData_sitename){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_sitename, 'text', 413083));
    }
    
    // ???????? ???????????????? ???????????????????? ??????????????????
    if (isset($importedLeadData_srcCheckbox) && $importedLeadData_srcCheckbox){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_srcCheckbox, 'multiselect', 969401));
    }

    // ???????????????? ??????????????????
    if (isset($importedLeadData_src) && $importedLeadData_src){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_src, 'text', 381167));
    }

    // ???????????????????? ????????
    if (isset($importedLeadData_files) && $importedLeadData_files){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_files, 'text', 976483));
    }

    // ???????????????????????????????????? ?? ??????????????
    if (isset($importedLeadData_clinicToRegister) && $importedLeadData_clinicToRegister){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_clinicToRegister, 'text', 464085));
    }

    // ???????????????????? ?? ??????????????
    if (isset($importedLeadData_docToRegister) && $importedLeadData_docToRegister){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_docToRegister, 'text', 464093));
    }

    // ?????????? ?????? ????????????
    if (isset($importedLeadData_timeToCall) && $importedLeadData_timeToCall){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_timeToCall, 'text', 382275));
    }

    // ???????????????????????? ????????????
    if (isset($importedLeadData_interested) && $importedLeadData_interested){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_interested, 'text', 382217));
    }

    // ??????????????
    if (isset($importedLeadData_age) && $importedLeadData_age){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_age, 'text', 381015));
    }
    // ??????
    if (isset($importedLeadData_sex) && $importedLeadData_sex){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_sex, 'text', 381017));
    }
    // ??????????????????????????????????
    if (isset($importedLeadData_selfdependence) && $importedLeadData_selfdependence){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_selfdependence, 'text', 381021));
    }
    // ?????????????? ????????. ??????????????????????
    if (isset($importedLeadData_psycho) && $importedLeadData_psycho){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_psycho, 'text', 381023));
    }
    // ???????????????? ???????? ????????????
    if (isset($importedLeadData_arrivaldate) && $importedLeadData_arrivaldate){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_arrivaldate, 'text', 381013));
    }
    // ??????-???? ????????????
    if (isset($importedLeadData_roomPlaces) && $importedLeadData_roomPlaces){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_roomPlaces, 'text', 381163));
    }

    // ????????????
    if (isset($importedLeadData_travelFrom) && $importedLeadData_travelFrom){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_travelFrom, 'text', 382429));
    }
    // ????????
    if (isset($importedLeadData_travelTo) && $importedLeadData_travelTo){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_travelTo, 'text', 382431));
    }
    // ??????????. ????????????
    if (isset($importedLeadData_preEstimate) && $importedLeadData_preEstimate){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_preEstimate, 'text', 381139));
    }
    // ???????????? ??????????
    if (isset($importedLeadData_nightTariff) && $importedLeadData_nightTariff){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_nightTariff, 'text', 381141));
    }
    // ?????????????????? ??????????????????
    if (isset($importedLeadData_sanitars) && $importedLeadData_sanitars){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_sanitars, 'select', 381161));
    }

    // ???????? ?????? ??????????????????
    if (isset($importedLeadData_excursion) && $importedLeadData_excursion){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_excursion, 'text', 381075));
    }
    // ??????????????????????
    if (isset($importedLeadData_comment) && $importedLeadData_comment){
      $CustomFieldsValues->add($this->createFieldValue($importedLeadData_comment, 'text', 381019));
    }


    // ???????????? ?????????????? ???????????????? ?????? ?????????????????? ????????
    $lead->setCustomFieldsValues($CustomFieldsValues);
    /**
     * ?? ???????????? ??????????????????
     */


    // ???????????????? ???????? ????????
    $this->log ? $this->log->write('?????????????????? ???????? ????????') : false;
    if (isset($tagsCollection_lead)) $lead->setTags($tagsCollection_lead);

    // ???????????????? ?????????????? ????????
    if (isset($importedLeadData_pipelineId) && $importedLeadData_pipelineId){
      $this->log ? $this->log->write('?????????????????? ?????????????? ????????') : false;
      $lead->setPipelineId($importedLeadData_pipelineId);
    }

    // ???????????? ?? ????????????????
    $leadsCollection = new LeadsCollection();
    $leadsCollection->add($lead);

    $this->log ? $this->log->write('???????????????? ???????? ?? ??????') : false;
    try {
      $leadsCollection = $leadsService->add($leadsCollection);
      $lead_id = $leadsCollection[0]->id;
      if(isset($importedLeadData_companyName) && $importedLeadData_companyName) {
        //???????????????? ????????????????
        $company = new CompanyModel();
        $company->setName($importedLeadData_companyName);

        $companiesCollection = new CompaniesCollection();
        $companiesCollection->add($company);
        try {
          $apiClient->companies()->add($companiesCollection);
        } catch (AmoCRMApiException $e) {
          //printError($e);

          $logdata = '????????????: '.$e->getTitle().', ??????: '.$e->getCode().', ?????????? ????????????:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
          $this->log ? $this->log->write($logdata) : false;

	  // trigger error
	  trigger_error($logdata, E_USER_WARNING);

          return false; //die;
        }

        $links = new LinksCollection();
        $links->add($contact);
        try {
          $apiClient->companies()->link($company, $links);
        } catch (AmoCRMApiException $e) {
          //printError($e);

          $logdata = '????????????: '.$e->getTitle().', ??????: '.$e->getCode().', ?????????? ????????????:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
          $this->log ? $this->log->write($logdata) : false;

	  // trigger error
	  trigger_error($logdata, E_USER_WARNING);

	  return false; //die;
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

          $logdata = '????????????: '.$e->getTitle().', ??????: '.$e->getCode().', ?????????? ????????????:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
          $this->log ? $this->log->write($logdata) : false;

          // trigger error
	  trigger_error($logdata, E_USER_WARNING);

          return false; //die;
        }
      }
      $this->log ? $this->log->write('Success! ????????????????????.') : false;
      return $lead_id;
    } catch (AmoCRMApiException $e) {
      // printError($e);

      $logdata = '????????????: '.$e->getTitle().', ??????: '.$e->getCode().', ?????????? ????????????:'.PHP_EOL.var_export($e->getLastRequestInfo(), true);
      $this->log ? $this->log->write($logdata) : false;

      // trigger error
      trigger_error($logdata, E_USER_WARNING);

      return false; //die;
    }

  }
}
