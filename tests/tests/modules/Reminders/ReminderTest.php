<?php

class jsAlertsLocal extends jsAlerts
{
    var $alertTriggered = false;
    var $alertTriggeredCount = 0;

    function addAlert($type, $name, $subtitle, $description, $countdown, $redirect='')
    {
        $this->alertTriggered = true;
        $this->alertTriggeredCount += 1;
        print("\naddAlert " . $subtitle . " Countdown " . $countdown);
    }

    function getAlertTriggered()
    {
        return $this->alertTriggeredCount;
    }
    function clearAlertTriggered()
    {
        $this->alertTriggered = false;
        $this->alertTriggeredCount = 0;
    }
}

class ReminderCommonTest
{
    var $alert;
    var $call;
    var $reminder;
    var $reminder_invitee;

    function setup($reminderTimeOffset, $reminderTime, $userDateFormat, $userTimeFormat, $userTimezone)
    {
        global $current_user;
        global $timedate;

        //unset and reconnect Db to resolve mysqli fetch exeception
        global $db;
        unset ($db->database);
        $db->checkConnection();

	    $this->alert = new jsAlertsLocal();
        $this->alert->clearAlertTriggered();

        $current_user = new User();
        $current_user->retrieve('1');
	    $current_user->loadPreferences();

        $current_user->setPreference('datef', $userDateFormat);
        $current_user->setPreference('timef', $userTimeFormat);
        $current_user->savePreferencesToDB();

        // we need to clear the timedate format cache as we
        // have just changed it
        $cacheKey = $timedate->get_date_time_format_cache_key($current_user);
        sugar_cache_clear($cacheKey);

//		$this->assertTrue(isset($result['userGmt']));
//		$this->assertTrue(isset($result['userGmtOffset']));

        // create a call to link to the reminder
        $this->call = new Call();
        $this->call->name = 'testcall1';
        $this->call->duration_hours = '1';
        $this->call->duration_minutes = '10';
        $this->call->status = 'Planned';
        $this->call->direction = 'Inbound';
        $this->call->description = 'some text';
        $this->call->assigned_user_id = 1;
        $this->call->created_by = 1;
        $this->call->modified_user_id = 1;

        // set the call start date and time
        // this must be in GMT timezone as it is saved to the database
        //$call->date_start = '2016-09-26 08:00:00';
        $start = $timedate->getNow()->modify("+{$reminderTimeOffset} seconds")->asDb();

        $this->call->date_start = $start;

	    // TODO
        $this->call->update_vcal = false;

        $this->call->id = $this->call->save();

        // due to cacheing in the BeanFactory the date time is
        // returned as GMT rather than User timezone as it normally is
        // so we manually set to User timezone
        $this->call->date_start = $timedate->to_display_date_time($start, false);

/*
    // Save 10 beans to flush the cache
    $x = new Call();
    BeanFactory::registerBean('Calls', $x, 1);
    BeanFactory::registerBean('Calls', $x, 2);
    BeanFactory::registerBean('Calls', $x, 3);
    BeanFactory::registerBean('Calls', $x, 4);
    BeanFactory::registerBean('Calls', $x, 5);
    BeanFactory::registerBean('Calls', $x, 6);
    BeanFactory::registerBean('Calls', $x, 7);
    BeanFactory::registerBean('Calls', $x, 8);
    BeanFactory::registerBean('Calls', $x, 9);
    BeanFactory::registerBean('Calls', $x, 10);
    BeanFactory::registerBean('Calls', $x, 11);
    BeanFactory::registerBean('Calls', $x, 12);

    $relatedEvent = BeanFactory::getBean('Calls', $this->call->id);
    print("\nBean: " . $relatedEvent->date_start);
*/   
        // create a new reminder and link to the call
        $this->reminder = new Reminder();

        $this->reminder->name = 'test3';
        $this->reminder->popup = '1';
        $this->reminder->email = '0';
        $this->reminder->email_sent = false;
        $this->reminder->timer_popup = $reminderTime;
        $this->reminder->timer_email = 0;
        $this->reminder->related_event_module = 'Calls';
        $this->reminder->related_event_module_id = $this->call->id;

        $this->reminder->id = $this->reminder->save();

        // create the reminder invitee
        $this->reminder_invitee = new Reminder_Invitee();

        $this->reminder_invitee->name = 'test3';
        $this->reminder_invitee->reminder_id = $this->reminder->id;
        $this->reminder_invitee->related_invitee_module = 'Users';
        $this->reminder_invitee->related_invitee_module_id = 1;

        $this->reminder_invitee->id = $this->reminder_invitee->save();
    }

    function cleanup()
    {
        // cleanup
        $this->call->mark_deleted($this->call->id);
        $this->reminder->mark_deleted($this->reminder->id);
        $this->reminder_invitee->mark_deleted($this->reminder_invitee->id);
    }

    function __destruct()
    {
    }
}

class ReminderTest extends PHPUnit_Framework_TestCase
{
    // test that the object can be created
    public function testReminder()
    {
        //execute the contructor and check for the Object type and  attributes
        $reminder = new Reminder();
        $this->assertInstanceOf('Reminder', $reminder);
        $this->assertInstanceOf('SugarBean', $reminder);

        $this->assertAttributeEquals('Reminders', 'module_dir', $reminder);
        $this->assertAttributeEquals('Reminder', 'object_name', $reminder);
        $this->assertAttributeEquals('reminders', 'table_name', $reminder);
        $this->assertAttributeEquals(true, 'new_schema', $reminder);
        $this->assertAttributeEquals(false, 'importable', $reminder);
        $this->assertAttributeEquals(false, 'tracker_visibility', $reminder);
        $this->assertAttributeEquals(true, 'disable_row_level_security', $reminder);
    }

/*
TODO why can a new call not be created and saved
    public function testSaveAndMarkDeleted()
    {
    	//unset and reconnect Db to resolve mysqli fetch exeception
    	global $db;
    	unset ($db->database);
    	$db->checkConnection();

        $reminder = new Reminder();

        $call = new Call();

        $call->name = 'test';
        $call->id = $call->save();
    }
*/
    // test that an object can be deleted
    public function testSaveAndMarkDeleted()
    {
        $reminder = new Reminder();

        $reminder->name = 'test1';
        $reminder->id = $reminder->save();

        //test for record ID to verify that record is saved
        $this->assertTrue(isset($reminder->id));
        $this->assertEquals(36, strlen($reminder->id));

        //mark the record as deleted and verify that this record cannot be retrieved anymore.
        $reminder->mark_deleted($reminder->id);
        $result = $reminder->retrieve($reminder->id);
        $this->assertEquals(null, $result);
    }

    // test that an alert is not created when there is no related record
    public function testaddNotifications_no_related()
    {
    	global $current_user;

    	//unset and reconnect Db to resolve mysqli fetch exeception
    	global $db;
    	unset ($db->database);
    	$db->checkConnection();

        // Create a new reminder but without a related event
        $reminder = new Reminder();

        $reminder->name = 'test2';
        $reminder->popup = '1';
        $reminder->email = '0';
        $reminder->email_sent = false;
        $reminder->timer_popup = 300;
        $reminder->timer_email = 300;
        $reminder->related_event_module = '';
        $reminder->related_event_module_id = '';

        $reminder->id = $reminder->save();
        
        //test for record ID to verify that record is saved
        $this->assertTrue(isset($reminder->id));
        $this->assertEquals(36, strlen($reminder->id));

    	$alert = new jsAlertsLocal();
        $alert->clearAlertTriggered();

        $current_user = new User();
        $current_user->retrieve('1');

        // trigger the notification
        $reminder->addNotifications($alert);

    	// check that no alert happened
        $this->assertEquals(0, $alert->getAlertTriggered());

        // cleanup
        $reminder->mark_deleted($reminder->id);
    }

    // test an alert when there is no start time set
    public function testaddNotifications_no_start()
    {
        global $current_user;
        global $timedate;

        //unset and reconnect Db to resolve mysqli fetch exeception
        global $db;
        unset ($db->database);
        $db->checkConnection();

	    $alert = new jsAlertsLocal();
        $alert->clearAlertTriggered();

        $current_user = new User();
        $current_user->retrieve('1');
	    $current_user->loadPreferences();

        // create a call to link to the reminder
        $call = new Call();
        $call->name = 'testcall2';
        $call->duration_hours = '1';
        $call->duration_minutes = '10';
        $call->status = 'Planned';
        $call->direction = 'Inbound';
        $call->description = 'some text';
        $call->assigned_user_id = 1;
        $call->created_by = 1;
        $call->modified_user_id = 1;

        // set the call start date and time to null
        $call->date_start = null;

	    // TODO
        $call->update_vcal = false;

        $call->id = $call->save();

        // create a new reminder and link to the call
        $reminder = new Reminder();

        $reminder->name = 'test4';
        $reminder->popup = '1';
        $reminder->email = '0';
        $reminder->email_sent = false;
        $reminder->timer_popup = 300;
        $reminder->timer_email = 0;
        $reminder->related_event_module = 'Calls';
        $reminder->related_event_module_id = $call->id;

        $reminder->id = $reminder->save();

        // create the reminder invitee
        $reminder_invitee = new Reminder_Invitee();

        $reminder_invitee->name = 'test4';
        $reminder_invitee->reminder_id = $reminder->id;
        $reminder_invitee->related_invitee_module = 'Users';
        $reminder_invitee->related_invitee_module_id = 1;

        $reminder_invitee->id = $reminder_invitee->save();

        //test for record ID to verify that record is saved
        $this->assertTrue(isset($reminder->id));
        $this->assertEquals(36, strlen($reminder->id));

        // trigger the notification
        $reminder->addNotifications($alert);

        // when there is no start time it defaults to 
        // the the current time so check that 1 alert happened
        $this->assertEquals(1, $alert->getAlertTriggered());

        $call->mark_deleted($call->id);
        $reminder->mark_deleted($reminder->id);
        $reminder_invitee->mark_deleted($reminder_invitee->id);
    }


/*

Y-m-d H:i
Y.m.d h:ia
Y.m.d h:iA
Y.m.d h:i a
*/

    // test a notification 10 mins (600s) away with 5 mins (300s) alert 
    public function testaddNotifications_normal()
    {
        $test = new ReminderCommonTest();

        $test->setup(900, 300, 'Y-m-d', 'H:i', '');

        //test for record ID to verify that record is saved
        $this->assertTrue(isset($test->reminder->id));
        $this->assertEquals(36, strlen($test->reminder->id));

        // trigger the notification
        $test->reminder->addNotifications($test->alert);

        // check that 1 alert happened
        $this->assertEquals(1, $test->alert->getAlertTriggered());

        $test->cleanup();
    }

    // test a notification now (60s) away with 5 mins (300s) alert 360 300
    // needs to be at leat 60s becasue seconds get dropped
    // when reading from the database
    public function testaddNotifications_edge_min()
    {
        $test = new ReminderCommonTest();

        $test->setup(360, 300, 'Y-m-d', 'H:i', '');

        //test for record ID to verify that record is saved
        $this->assertTrue(isset($test->reminder->id));
        $this->assertEquals(36, strlen($test->reminder->id));

        // trigger the notification
        $test->reminder->addNotifications($test->alert);

        // check that 1 alert happened
        $this->assertEquals(1, $test->alert->getAlertTriggered());

        $test->cleanup();
    }

    // test a notification the max time away with 5 mins (300s) alert
    public function testaddNotifications_edge_max()
    {
        global $app_list_strings;

        $test = new ReminderCommonTest();

        $test->setup($app_list_strings['reminder_max_time'], 300, 'Y-m-d', 'H:i', '');

        //test for record ID to verify that record is saved
        $this->assertTrue(isset($test->reminder->id));
        $this->assertEquals(36, strlen($test->reminder->id));

        // trigger the notification
        $test->reminder->addNotifications($test->alert);

        // check that 1 alert happened
        $this->assertEquals(1, $test->alert->getAlertTriggered());

        $test->cleanup();
    }

    // test a notification with date format m/d/y
    public function testaddNotifications_date_mdy()
    {
        $test = new ReminderCommonTest();

        $test->setup(900, 300, 'm/d/y', 'H:i', '');

        //test for record ID to verify that record is saved
        $this->assertTrue(isset($test->reminder->id));
        $this->assertEquals(36, strlen($test->reminder->id));

        // trigger the notification
        $test->reminder->addNotifications($test->alert);

        // check that 1 alert happened
        $this->assertEquals(1, $test->alert->getAlertTriggered());

        $test->cleanup();
    }

    // test a notification with date format d/m/y
    public function testaddNotifications_date_dmy()
    {
        $test = new ReminderCommonTest();

        $test->setup(900, 300, 'd/m/y', 'H:i', '');

        //test for record ID to verify that record is saved
        $this->assertTrue(isset($test->reminder->id));
        $this->assertEquals(36, strlen($test->reminder->id));

        // trigger the notification
        $test->reminder->addNotifications($test->alert);

        // check that 1 alert happened
        $this->assertEquals(1, $test->alert->getAlertTriggered());

        $test->cleanup();
    }

}
