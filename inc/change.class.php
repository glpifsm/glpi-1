<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2013 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/** @file
* @brief
*/

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Change Class
**/
class Change extends CommonITILObject {

   // From CommonDBTM
   public $dohistory                   = true;
   static protected $forward_entity_to = array('ChangeValidation');

   // From CommonITIL
   public $userlinkclass               = 'Change_User';
   public $grouplinkclass              = 'Change_Group';
   public $supplierlinkclass           = 'Change_Supplier';

   static $rightname                   = 'change';
   protected $usenotepadrights         = true;

   const MATRIX_FIELD                  = 'priority_matrix';
   const URGENCY_MASK_FIELD            = 'urgency_mask';
   const IMPACT_MASK_FIELD             = 'impact_mask';
   const STATUS_MATRIX_FIELD           = 'change_status';


   const READMY                        = 1;
   const READALL                       = 1024;



   /**
    * Name of the type
    *
    * @param $nb : number of item in the type (default 0)
   **/
   static function getTypeName($nb=0) {
      return _n('Change','Changes',$nb);
   }


   function canAdminActors() {
      return Session::haveRight(self::$rightname, UPDATE);
   }


   function canAssign() {
      return Session::haveRight(self::$rightname, UPDATE);
   }


   function canAssignToMe() {
      return Session::haveRight(self::$rightname, UPDATE);
   }


   function canSolve() {

      return (self::isAllowedStatus($this->fields['status'], self::SOLVED)
              // No edition on closed status
              && !in_array($this->fields['status'], $this->getClosedStatusArray())
              && (Session::haveRight(self::$rightname, UPDATE)
                  || (Session::haveRight(self::$rightname, self::READMY)
                      && ($this->isUser(CommonITILActor::ASSIGN, Session::getLoginUserID())
                          || (isset($_SESSION["glpigroups"])
                              && $this->haveAGroup(CommonITILActor::ASSIGN,
                                                   $_SESSION["glpigroups"]))))));
   }


   static function canView() {
      return Session::haveRightsOr(self::$rightname, array(self::READALL, self::READMY));
   }


   /**
    * Is the current user have right to show the current change ?
    *
    * @return boolean
   **/
   function canViewItem() {

      if (!Session::haveAccessToEntity($this->getEntityID())) {
         return false;
      }
      return (Session::haveRight(self::$rightname, self::READALL)
              || (Session::haveRight(self::$rightname, self::READMY)
                  && ($this->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID())
                      || $this->isUser(CommonITILActor::OBSERVER, Session::getLoginUserID())
                      || (isset($_SESSION["glpigroups"])
                          && ($this->haveAGroup(CommonITILActor::REQUESTER, $_SESSION["glpigroups"])
                              || $this->haveAGroup(CommonITILActor::OBSERVER,
                                                   $_SESSION["glpigroups"])))
                      || ($this->isUser(CommonITILActor::ASSIGN, Session::getLoginUserID())
                          || (isset($_SESSION["glpigroups"])
                              && $this->haveAGroup(CommonITILActor::ASSIGN,
                                                   $_SESSION["glpigroups"]))))));
   }


   /**
    * Is the current user have right to approve solution of the current change ?
    *
    * @return boolean
   **/
   function canApprove() {

      return (($this->fields["users_id_recipient"] === Session::getLoginUserID())
              || $this->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID())
              || (isset($_SESSION["glpigroups"])
                  && $this->haveAGroup(CommonITILActor::REQUESTER, $_SESSION["glpigroups"])));
   }


   /**
    * Is the current user have right to create the current change ?
    *
    * @return boolean
   **/
   function canCreateItem() {

      if (!Session::haveAccessToEntity($this->getEntityID())) {
         return false;
      }
      return Session::haveRight(self::$rightname, CREATE);
   }


   function pre_deleteItem() {

      NotificationEvent::raiseEvent('delete', $this);
      return true;
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if (static::canView()) {
         $nb = 0;
         switch ($item->getType()) {
            case __CLASS__ :
               $ong = array(1 => __('Analysis'),
                            2 => __('Plans'),
                            3 => __('Solution'));
               if ($item->canUpdate()) {
                  $ong[4] = __('Statistics');
               }
               return $ong;
         }
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      switch ($item->getType()) {
         case __CLASS__ :
            switch ($tabnum) {
               case 1 :
                  $item->showAnalysisForm();
                  break;

               case 2 :
                  $item->showPlanForm();
                  break;

               case 3 :
                  if (!isset($_POST['load_kb_sol'])) {
                     $_POST['load_kb_sol'] = 0;
                  }
                  $item->showSolutionForm($_POST['load_kb_sol']);
                  break;
               case 4 :
                  $item->showStats();
                  break;
            }
            break;
      }
      return true;
   }


   function defineTabs($options=array()) {
      $ong = array();
      // show related tickets and changes
      $this->addDefaultFormTab($ong);
      $this->addStandardTab(__CLASS__, $ong, $options);
      $this->addStandardTab('ChangeValidation', $ong, $options);
      $this->addStandardTab('ChangeTask', $ong, $options);
      $this->addStandardTab('ChangeCost', $ong, $options);
      $this->addStandardTab('Change_Project', $ong, $options);
      $this->addStandardTab('Change_Problem', $ong, $options);
      $this->addStandardTab('Change_Ticket', $ong, $options);
      $this->addStandardTab('Document_Item', $ong, $options);
      $this->addStandardTab('Change_Item', $ong, $options);
      $this->addStandardTab('Notepad', $ong, $options);
      $this->addStandardTab('Log', $ong, $options);

      return $ong;
   }


   function cleanDBonPurge() {
      global $DB;


      $query1 = "DELETE
                 FROM `glpi_changetasks`
                 WHERE `changes_id` = '".$this->fields['id']."'";
      $DB->query($query1);

      $cp = new Change_Problem();
      $cp->cleanDBonItemDelete(__CLASS__, $this->fields['id']);

      $ct = new Change_Ticket();
      $ct->cleanDBonItemDelete(__CLASS__, $this->fields['id']);
      
      $cp = new Change_Project();
      $cp->cleanDBonItemDelete(__CLASS__, $this->fields['id']);

      $ci = new Change_Item();
      $ci->cleanDBonItemDelete(__CLASS__, $this->fields['id']);

      $cv = new ChangeValidation();
      $cv->cleanDBonItemDelete(__CLASS__, $this->fields['id']);

      $cc = new ChangeCost();
      $cc->cleanDBonItemDelete(__CLASS__, $this->fields['id']);
      
      parent::cleanDBonPurge();
   }


   function prepareInputForUpdate($input) {

      // Get change : need for comparison
//       $this->getFromDB($input['id']);

      $input = parent::prepareInputForUpdate($input);
      return $input;
   }


   function pre_updateInDB() {
      parent::pre_updateInDB();
   }


   function post_updateItem($history=1) {
      global $CFG_GLPI;

      $donotif =  count($this->updates);

      if (isset($this->input['_forcenotif'])) {
         $donotif = true;
      }

      if (isset($this->input['_disablenotif'])) {
         $donotif = false;
      }

      if ($donotif && $CFG_GLPI["use_mailing"]) {
         $mailtype = "update";
         if (isset($this->input["status"]) && $this->input["status"]
             && in_array("status",$this->updates)
             && in_array($this->input["status"], $this->getSolvedStatusArray())) {

            $mailtype = "solved";
         }

         if (isset($this->input["status"]) && $this->input["status"]
             && in_array("status",$this->updates)
             && in_array($this->input["status"], $this->getClosedStatusArray())) {

            $mailtype = "closed";
         }

         // Read again change to be sure that all data are up to date
         $this->getFromDB($this->fields['id']);
         NotificationEvent::raiseEvent($mailtype, $this);
      }

      /// TODO auto solve tickets / changes ?

   }


   function prepareInputForAdd($input) {

      $input =  parent::prepareInputForAdd($input);
      return $input;
   }


   function post_addItem() {
      global $CFG_GLPI;

      parent::post_addItem();

      if (isset($this->input['_tickets_id'])) {
         $ticket = new Ticket();
         if ($ticket->getFromDB($this->input['_tickets_id'])) {
            $pt = new Change_Ticket();
            $pt->add(array('tickets_id' => $this->input['_tickets_id'],
                           'changes_id' => $this->fields['id']));

            if (!empty($ticket->fields['itemtype']) && $ticket->fields['items_id']>0) {
               $it = new Change_Item();
               $it->add(array('changes_id' => $this->fields['id'],
                              'itemtype'   => $ticket->fields['itemtype'],
                              'items_id'   => $ticket->fields['items_id']));
            }
         }
      }

      if (isset($this->input['_problems_id'])) {
         $problem = new Problem();
         if ($problem->getFromDB($this->input['_problems_id'])) {
            $cp = new Change_Problem();
            $cp->add(array('problems_id' => $this->input['_problems_id'],
                           'changes_id'  => $this->fields['id']));

            /// TODO add linked tickets and linked hardware (to problem and tickets)
            /// create standard function
         }
      }

      // Processing Email
      if ($CFG_GLPI["use_mailing"]) {
         // Clean reload of the change
         $this->getFromDB($this->fields['id']);

         $type = "new";
         if (isset($this->fields["status"])
             && in_array($this->input["status"], $this->getSolvedStatusArray())) {
            $type = "solved";
         }
         NotificationEvent::raiseEvent($type, $this);
      }

   }


   /**
    * Get default values to search engine to override
   **/
   static function getDefaultSearchRequest() {

      $search = array('field'      => array(0 => 12),
                      'searchtype' => array(0 => 'equals'),
                      'contains'   => array(0 => 'notold'),
                      'sort'       => 19,
                      'order'      => 'DESC');

      return $search;
   }


   function getSearchOptions() {

      $tab = array();

      $tab += $this->getSearchOptionsMain();

      $tab += $this->getSearchOptionsActors();

      $tab['analysis']          = __('Control list');

      $tab[60]['table']         = $this->getTable();
      $tab[60]['field']         = 'impactcontent';
      $tab[60]['name']          = __('Impact');
      $tab[60]['massiveaction'] = false;
      $tab[60]['datatype']      = 'text';

      $tab[61]['table']         = $this->getTable();
      $tab[61]['field']         = 'controlistcontent';
      $tab[61]['name']          = __('Control list');
      $tab[61]['massiveaction'] = false;
      $tab[61]['datatype']      = 'text';

      $tab[62]['table']         = $this->getTable();
      $tab[62]['field']         = 'rolloutplancontent';
      $tab[62]['name']          = __('Deployment plan');
      $tab[62]['massiveaction'] = false;
      $tab[62]['datatype']      = 'text';

      $tab[63]['table']         = $this->getTable();
      $tab[63]['field']         = 'backoutplancontent';
      $tab[63]['name']          = __('Backup plan');
      $tab[63]['massiveaction'] = false;
      $tab[63]['datatype']      = 'text';

      $tab[64]['table']         = $this->getTable();
      $tab[64]['field']         = 'checklistcontent';
      $tab[64]['name']          = __('Checklist');
      $tab[64]['massiveaction'] = false;
      $tab[64]['datatype']      = 'text';

      $tab[90]['table']         = $this->getTable();
      $tab[90]['field']         = 'notepad';
      $tab[90]['name']          = __('Notes');
      $tab[90]['massiveaction'] = false;
      $tab[90]['datatype']      = 'text';

      $tab += ChangeValidation::getSearchOptionsToAdd();

      $tab += ChangeTask::getSearchOptionsToAdd();

      $tab += $this->getSearchOptionsSolution();

      $tab += TicketCost::getSearchOptionsToAdd();

      return $tab;
   }


   /**
    * get the change status list
    * To be overridden by class
    *
    * @param $withmetaforsearch boolean (default false)
    *
    * @return an array
   **/
   static function getAllStatusArray($withmetaforsearch=false) {

      // new, evaluation, approbation, process (sub status : test, qualification, applied), review, closed, abandoned

      /// TODO to be done : try to keep closed. Is abandonned usable ?
      /// TODO define standard function to check solved / closed status

      // To be overridden by class
      $tab = array(self::INCOMING      => _x('change', 'New'),
                   self::EVALUATION    => __('Evaluation'),
                   self::APPROVAL      => __('Approval'),
                   self::ACCEPTED      => _x('change', 'Accepted'),
                   self::WAITING       => __('Pending'),
//                   self::ACCEPTED      => __('Processing (assigned)'),
//                   self::PLANNED        => __('Processing (planned)'),
                   self::TEST          => __('Test'),
                   self::QUALIFICATION => __('Qualification'),
                   self::SOLVED        => __('Applied'),
                   self::OBSERVED      => __('Review'),
                   self::CLOSED        => _x('change', 'Closed'),
//                   'abandoned'     => __('Abandonned'), // managed using dustbin ?
   );

      if ($withmetaforsearch) {
         $tab['notold']    = _x('change', 'Not solved');
         $tab['notclosed'] = _x('change', 'Not closed');
         $tab['process']   = __('Processing');
         $tab['old']       = _x('change', 'Solved + Closed');
         $tab['all']       = __('All');
      }
      return $tab;
   }


   /**
    * Get the ITIL object closed status list
    * To be overridden by class
    *
    * @since version 0.83
    *
    * @return an array
   **/
   static function getClosedStatusArray() {

      // To be overridden by class
      $tab = array(self::CLOSED/*, 'abandoned'*/);
      return $tab;
   }


   /**
    * Get the ITIL object solved or observe status list
    * To be overridden by class
    *
    * @since version 0.83
    *
    * @return an array
   **/
   static function getSolvedStatusArray() {
      // To be overridden by class
      $tab = array(self::OBSERVED, self::SOLVED);
      return $tab;
   }

   /**
    * Get the ITIL object new status list
    *
    * @since version 0.83.8
    *
    * @return an array
   **/
   static function getNewStatusArray() {
      return array(self::INCOMING, self::ACCEPTED, self::EVALUATION, self::APPROVAL);
   }

   /**
    * Get the ITIL object test, qualification or accepted status list
    * To be overridden by class
    *
    * @since version 0.83
    *
    * @return an array
   **/
   static function getProcessStatusArray() {

      // To be overridden by class
      $tab = array(self::ACCEPTED, self::QUALIFICATION, self::TEST);
      return $tab;
   }


   function showForm($ID, $options=array()) {
      global $CFG_GLPI, $DB;

      if (!static::canView()) {
        return false;
      }

      // In percent
      $colsize1 = '13';
      $colsize2 = '37';

      // Set default options
      if (!$ID) {
         $values = array('_users_id_requester'       => Session::getLoginUserID(),
                         '_users_id_requester_notif' => array('use_notification' => 1),
                         '_groups_id_requester'      => 0,
                         '_users_id_assign'          => 0,
                         '_users_id_assign_notif'    => array('use_notification' => 1),
                         '_groups_id_assign'         => 0,
                         '_users_id_observer'        => 0,
                         '_users_id_observer_notif'  => array('use_notification' => 1),
                         '_groups_id_observer'       => 0,
                         '_suppliers_id_assign'      => 0,
                         'priority'                  => 3,
                         'urgency'                   => 3,
                         'impact'                    => 3,
                         'content'                   => '',
                         'entities_id'               => $_SESSION['glpiactive_entity'],
                         'name'                      => '',
                         'itilcategories_id'         => 0);
         foreach ($values as $key => $val) {
            if (!isset($options[$key])) {
               $options[$key] = $val;
            }
         }

         if (isset($options['tickets_id'])) {
            $ticket = new Ticket();
            if ($ticket->getFromDB($options['tickets_id'])) {
               $options['content']             = $ticket->getField('content');
               $options['name']                = $ticket->getField('name');
               $options['impact']              = $ticket->getField('impact');
               $options['urgency']             = $ticket->getField('urgency');
               $options['priority']            = $ticket->getField('priority');
               $options['itilcategories_id']   = $ticket->getField('itilcategories_id');
            }
         }

         if (isset($options['problems_id'])) {
            $problem = new Problem();
            if ($problem->getFromDB($options['problems_id'])) {
               $options['content']             = $problem->getField('content');
               $options['name']                = $problem->getField('name');
               $options['impact']              = $problem->getField('impact');
               $options['urgency']             = $problem->getField('urgency');
               $options['priority']            = $problem->getField('priority');
               $options['itilcategories_id']   = $problem->getField('itilcategories_id');
            }
         }
      }

      if ($ID > 0) {
         $this->check($ID, READ);
      } else {
         // Create item
         $this->check(-1, CREATE, $options);
      }

      $showuserlink = 0;
      if (User::canView()) {
         $showuserlink = 1;
      }

      $this->showFormHeader($options);


      echo "<tr class='tab_bg_1'>";
      echo "<th class='left' width='$colsize1%'>".__('Opening date')."</th>";
      echo "<td class='left' width='$colsize2%'>";

      if (isset($options['tickets_id'])) {
         echo "<input type='hidden' name='_tickets_id' value='".$options['tickets_id']."'>";
      }
      if (isset($options['problems_id'])) {
         echo "<input type='hidden' name='_problems_id' value='".$options['problems_id']."'>";
      }
      $date = $this->fields["date"];
      if (!$ID) {
         $date = date("Y-m-d H:i:s");
      }
      Html::showDateTimeField("date", array('value'      => $date,
                                            'timestep'   => 1,
                                            'maybeempty' => false));
      echo "</td>";
      echo "<th width='$colsize1%'>".__('Due date')."</th>";
      echo "<td width='$colsize2%' class='left'>";

      if ($this->fields["due_date"] == 'NULL') {
         $this->fields["due_date"] = '';
      }
      Html::showDateTimeField("due_date", array('value'    => $this->fields["due_date"],
                                                'timestep' => 1));

      echo "</td></tr>";

      if ($ID) {
         echo "<tr class='tab_bg_1'><th>".__('By')."</th><td>";
         User::dropdown(array('name'   => 'users_id_recipient',
                              'value'  => $this->fields["users_id_recipient"],
                              'entity' => $this->fields["entities_id"],
                              'right'  => 'all'));
         echo "</td>";
         echo "<th>".__('Last update')."</th>";
         echo "<td>".Html::convDateTime($this->fields["date_mod"])."\n";
         if ($this->fields['users_id_lastupdater'] > 0) {
            printf(__('%1$s: %2$s'), __('By'),
                   getUserName($this->fields["users_id_lastupdater"], $showuserlink));
         }
         echo "</td></tr>";
      }

      if ($ID
          && (in_array($this->fields["status"], $this->getSolvedStatusArray())
              || in_array($this->fields["status"], $this->getClosedStatusArray()))) {
         echo "<tr class='tab_bg_1'>";
         echo "<th>".__('Date of solving')."</th>";
         echo "<td>";
         Html::showDateTimeField("solvedate", array('value'      => $this->fields["solvedate"],
                                                    'timestep'   => 1,
                                                    'maybeempty' => false));
         echo "</td>";
         if (in_array($this->fields["status"], $this->getClosedStatusArray())) {
            echo "<th>".__('Closing date')."</th>";
            echo "<td>";
            Html::showDateTimeField("closedate", array('value'      => $this->fields["closedate"],
                                                       'timestep'   => 1,
                                                       'maybeempty' => false));
            echo "</td>";
         } else {
            echo "<td colspan='2'>&nbsp;</td>";
         }
         echo "</tr>";
      }
      echo "</table>";

      echo "<table class='tab_cadre_fixe' id='mainformtable2'>";
      echo "<tr>";
      echo "<th width='$colsize1%'>".__('Status')."</th>";
      echo "<td width='$colsize2%'>";
      self::dropdownStatus(array('value'    => $this->fields["status"],
                                 'showtype' => 'allowed'));
      ChangeValidation::alertValidation($this, 'status');
      echo "</td>";
      echo "<th width='$colsize1%'>".__('Urgency')."</th>";
      echo "<td width='$colsize2%'>";
      // Only change during creation OR when allowed to change priority OR when user is the creator
      $idurgency = self::dropdownUrgency(array('value' => $this->fields["urgency"]));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<th>".__('Category')."</th>";
      echo "<td >";
      $opt = array('value'  => $this->fields["itilcategories_id"],
                   'entity' => $this->fields["entities_id"]);
      ITILCategory::dropdown($opt);
      echo "</td>";
      echo "<th>".__('Impact')."</th>";
      echo "<td>";
      $idimpact = self::dropdownImpact(array('value' => $this->fields["impact"]));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<th>".__('Total duration')."</th>";
      echo "<td>".parent::getActionTime($this->fields["actiontime"])."</td>";
      echo "<th class='left'>".__('Priority')."</th>";
      echo "<td>";
      $idpriority = parent::dropdownPriority(array('value'     => $this->fields["priority"],
                                                   'withmajor' => true));
      $idajax     = 'change_priority_' . mt_rand();
      echo "&nbsp;<span id='$idajax' style='display:none'></span>";
      $params = array('urgency'  => '__VALUE0__',
                      'impact'   => '__VALUE1__',
                      'priority' => 'dropdown_priority'.$idpriority);
      Ajax::updateItemOnSelectEvent(array('dropdown_urgency'.$idurgency,
                                          'dropdown_impact'.$idimpact),
                                    $idajax,
                                    $CFG_GLPI["root_doc"]."/ajax/priority.php", $params);
      echo "</td>";
      echo "</tr>";
      echo "</table>";

      $this->showActorsPartForm($ID,$options);

      echo "<table class='tab_cadre_fixe' id='mainformtable3'>";
      echo "<tr class='tab_bg_1'>";
      echo "<th width='$colsize1%'>".__('Title')."</th>";
      echo "<td colspan='3'>";
      echo "<input type='text' size='90' maxlength=250 name='name' ".
             " value=\"".Html::cleanInputText($this->fields["name"])."\">";
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<th>".__('Description')."</th>";
      echo "<td colspan='3'>";
      $rand = mt_rand();
      echo "<textarea id='content$rand' name='content' cols='90' rows='6'>".
             $this->fields["content"]."</textarea>";
      echo "</td>";
      echo "</tr>";
      $options['colspan'] = 3;
      $this->showFormButtons($options);

      return true;

   }


   /**
    * Form to add an analysis to a change
   **/
   function showAnalysisForm() {

      $this->check($this->getField('id'), READ);
      $canedit = $this->canEdit($this->getField('id'));

      $options            = array();
      $options['canedit'] = false;
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Impacts')."</td><td colspan='3'>";
      if ($canedit) {
         echo "<textarea id='impactcontent' name='impactcontent' rows='6' cols='110'>";
         echo $this->getField('impactcontent');
         echo "</textarea>";
      } else {
         echo $this->getField('impactcontent');
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Control list')."</td><td colspan='3'>";
      if ($canedit) {
         echo "<textarea id='controlistcontent' name='controlistcontent' rows='6' cols='110'>";
         echo $this->getField('controlistcontent');
         echo "</textarea>";
      } else {
         echo $this->getField('controlistcontent');
      }
      echo "</td></tr>";

      $options['candel']  = false;
      $options['canedit'] = $canedit;
      $this->showFormButtons($options);

   }

   /**
    * Form to add an analysis to a change
   **/
   function showPlanForm() {

      $this->check($this->getField('id'), READ);
      $canedit            = $this->canEdit($this->getField('id'));

      $options            = array();
      $options['canedit'] = false;
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Deployment plan')."</td><td colspan='3'>";
      if ($canedit) {
         echo "<textarea id='rolloutplancontent' name='rolloutplancontent' rows='6' cols='110'>";
         echo $this->getField('rolloutplancontent');
         echo "</textarea>";
      } else {
         echo $this->getField('rolloutplancontent');
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Backup plan')."</td><td colspan='3'>";
      if ($canedit) {
         echo "<textarea id='backoutplancontent' name='backoutplancontent' rows='6' cols='110'>";
         echo $this->getField('backoutplancontent');
         echo "</textarea>";
      } else {
         echo $this->getField('backoutplancontent');
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Checklist')."</td><td colspan='3'>";
      if ($canedit) {
         echo "<textarea id='checklistcontent' name='checklistcontent' rows='6' cols='110'>";
         echo $this->getField('checklistcontent');
         echo "</textarea>";
      } else {
         echo $this->getField('checklistcontent');
      }
      echo "</td></tr>";

      $options['candel']  = false;
      $options['canedit'] = $canedit;
      $this->showFormButtons($options);

   }


   /**
    * @since version 0.85
    *
    * @see commonDBTM::getRights()
    **/
   function getRights($interface='central') {

      $values = parent::getRights();
      unset($values[READ]);

      $values[self::READALL] = __('See all');
      $values[self::READMY]  = __('See (author)');

      return $values;
   }


   /**
    * Number of tasks of the problem
    *
    * @return followup count
   **/
   function numberOfTasks() {
      global $DB;
      // Set number of followups
      $query = "SELECT COUNT(*)
                FROM `glpi_changetasks`
                WHERE `changes_id` = '".$this->fields["id"]."'";
      $result = $DB->query($query);

      return $DB->result($result, 0, 0);
   }
}
?>
