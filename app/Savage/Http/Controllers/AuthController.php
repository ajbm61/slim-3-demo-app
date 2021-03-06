<?php

namespace Savage\Http\Controllers;

class AuthController extends Controller {
    public function get() {
    }

    public function getLogin() {
        return $this->render('auth/login');
    }

    public function postLogin() {
        if($this->data() === null) {
            $this->flash('error', 'Please fill out the fields!');
            return $this->response->withRedirect($this->router->pathFor('auth.login'));
        } else {
            $validator = $this->getValidator();
            $data = [
                'identifier' => $this->data()->identifier,
                'password' => $this->data()->password,
                'remember' => isset($this->data()->remember) ? 'on' : 'off',
            ];

            $this->getValidator()->validate([
                'identifier|E-mail or Username' => [$data['identifier'], 'required'],
                'password|Password' => [$data['password'], 'required'],
            ]);

            if($validator->passes()) {
                // Log the user in
                $user = $this->container->user->where('email', $data['identifier'])->orWhere('username', $data['identifier'])->first();

                if(!$user || !$this->container->util->verifyPassword($data['password'], $user->password)) {
                    $this->flashNow('error', 'The credentials you have entered are invalid.');
                    $this->flashNow('identifier', $data['identifier']);

                    return $this->render('auth/login', [
                        'errors' => $validator->errors()
                    ]);
                } else if($user && !(bool) $user->active) {
                    $this->flash('error', 'Your account is banned.');
                    return $this->redirectTo('auth.login');
                } else if($user && $this->container->util->verifyPassword($data['password'], $user->password)) {
                    \Savage\Http\Util\Session::set($this->container->settings['auth']['session'], $user->id);

                    if($data['remember'] === 'on') {
                        $rememberIdentifier = $this->container->util->genAlnumString(128);
                        $rememberToken = $this->container->util->genAlnumString(128);

                        $user->updateRememberCredentials($rememberIdentifier, $this->container->util->hash($rememberToken));

                        \Savage\Http\Util\Cookie::set($this->container->settings['auth']['remember'], "{$rememberIdentifier}.{$rememberToken}", \Carbon\Carbon::now()->addWeek(2)->timestamp);
                    }

                    return $this->redirectTo('home');
                }

                return $this->redirectTo('home');
            } else {
                // Are we going to need to flash all previous data se we can keep it in the input field?
                foreach ($data as $key => $value) {
                    $this->flashNow($key, $value);
                }

                $this->flashNow('error', 'You have some errors with your registration, please fix them and try again.');

                return $this->render('auth/login', [
                    'errors' => $validator->errors()
                ]);
            }
        }
    }

    public function getRegister() {
        return $this->render('auth/register');
    }

    public function postRegister() {
        if($this->data() === null) {
            $this->flash('error', 'Please fill out the fields!');
            return $this->redirectTo('auth.register');
        } else {
            $validator = $this->getValidator();
            $data = [
                'first_name' => $this->data()->first_name,
                'last_name' => $this->data()->last_name,
                'username' => $this->data()->username,
                'email' => $this->data()->email,
                'password' => $this->data()->password,
                'confirm_password' => $this->data()->confirm_password,
            ];

            $this->getValidator()->validate([
                'first_name|First Name' => [$data['first_name'], 'required|max(20)'],
                'last_name|Last Name' => [$data['last_name'], 'required|max(20)'],
                'username|Username' => [$data['username'], 'required|alnumDash|max(25)|uniqueUsername'],
                'email|E-Mail' => [$data['email'], 'required|email|max(50)|uniqueEmail'],
                'password|Password' => [$data['password'], 'required|min(6)|max(75)'],
                'confirm_password|Confirm Password' => [$data['confirm_password'], 'required|matches(password)'],
            ]);

            if($validator->passes()) {
                // Add the user to database and send them to login
                $user = $this->container->user->create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password' => $this->container->util->hashPassword($data['password']),
                ]);

                $user->permissions()->create(\Savage\Http\Auth\Permission\UserPermissions::$defaults);
                $this->flash('success', 'You have been registered! You can now login!');
                return $this->redirectTo('auth.login');
            } else {
                // Are we going to need to flash all previous data se we can keep it in the input field?
                foreach ($data as $key => $value) {
                    $this->flashNow($key, $value);
                }

                $this->flashNow('error', 'You have some errors with your registration, please fix them and try again.');

                return $this->render('auth/register', [
                    'errors' => $validator->errors()
                ]);
            }
        }
    }

    public function getSettings() {
        return $this->render('auth/settings');
    }

    public function getProfile() {
        return $this->redirectTo('auth.settings', null, "#profile");
    }

    public function postProfile() {
        if($this->data() === null) {
            $this->flash('error', 'Please fill out the fields!');
            return $this->redirectTo('auth.register');
        } else {
            $validator = $this->getValidator();
            $data = [
                'first_name' => $this->data()->first_name,
                'last_name' => $this->data()->last_name,
                'email' => $this->data()->email,
            ];

            $validator->validate([
                'first_name|First Name' => [$data['first_name'], 'required|max(20)'],
                'last_name|Last Name' => [$data['last_name'], 'required|max(20)'],
                'email|E-Mail' => [$data['email'], 'required|email|max(50)|uniqueEmail'],
            ]);

            if($validator->passes()) {
                if($this->isLoggedIn()) {
                    $this->getAuthUser()->update([
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'email' => $data['email'],
                    ]);

                    $this->flash('success', 'Your profile has been updated!');
                    return $this->redirectTo('auth.settings', null, "#profile");
                }

                return $this->redirectTo('home');
            } else {
                $this->flashNow('error', 'There were some errors while trying to update your profile, please fix them and try again.');

                return $this->render('auth/settings', [
                    'errors' => $validator->errors()
                ]);
            }
        }
    }

    public function getPassword() {
        return $this->redirectTo('auth.settings', null, "#password");
    }

    public function postPassword() {
        if($this->data() === null) {
            $this->flash('error', 'Please fill out the fields!');
            return $this->redirectTo('auth.register');
        } else {
            $validator = $this->getValidator();
            $data = [
                'current_password' => $this->data()->current_password,
                'new_password' => $this->data()->new_password,
                'confirm_new_password' => $this->data()->confirm_new_password,
            ];

            $validator->validate([
                'current_password|Current Password' => [$data['current_password'], 'required|matchesCurrentPassword'],
                'new_password|New Password' => [$data['new_password'], 'required|min(6)|max(75)'],
                'confirm_new_password|Confirm New Password' => [$data['confirm_new_password'], 'required|matches(new_password)'],
            ]);

            if($validator->passes()) {
                if($this->isLoggedIn()) {
                    $this->getAuthUser()->update([
                        'password' => $this->container->util->hashPassword($data['new_password']),
                    ]);

                    $this->flash('success', 'Your password has been changed!');
                    return $this->redirectTo('auth.settings', null, "#password");
                }

                return $this->redirectTo('home');
            } else {
                $this->flashNow('error', 'There were some errors while trying to change your password, please fix them and try again.');

                return $this->render('auth/settings', [
                    'errors' => $validator->errors()
                ]);
            }
        }
    }

    /**
    * Notifications
    */

    public function getNotifications() {
        return $this->render('auth/notifications');
    }

    /**
    * Direct Messages
    */

    public function getDirectMessages() {
        // I guess we can do this here....
        $usernamesIndex = $this->container->search->initIndex('demoapp_usernames');
        $users = $this->container->user->select('users.id', 'users.username')->get();

        $usernames = [];

        foreach ($users as $user) {
            $user['objectID'] = $user->id;
            $user['username'] = $user->username;
            array_push($usernames, $user);
        }

        $usernamesIndex->saveObjects($usernames);

        $uMessagesForCount = $this->container->directMessage->where('receiver_id', $this->container->site->auth->id)->where('viewed', false)->where('deleted', false)->get();
        $dMessagesForCount = $this->container->directMessage->where('receiver_id', $this->container->site->auth->id)->where('viewed', false)->where('deleted', true)->get();

        return $this->render('auth/messages', [
            'messages' => $this->container->site->auth->getDirectMessages(),
        ]);
    }

    public function getViewDirectMessage() {
        $messageId = $this->request->getAttribute('id');

        $rawMessage = $this->container->directMessage->where('id', $messageId)->first();

        // Check to see if the message exists and if it does, is the user able to view it.
        if(!$rawMessage || $rawMessage && $rawMessage->receiver_id !== $this->container->site->auth->id && $rawMessage->sender_id !== $this->container->site->auth->id) {
            return $this->redirectTo('auth.messages');
        }

        $message = $this->container->directMessage->where('direct_messages.id', $messageId)
            ->join('users as sender', 'direct_messages.sender_id', '=', 'sender.id')
            ->join('users as receiver', 'direct_messages.receiver_id', '=', 'receiver.id')
            ->select('direct_messages.*',
                'sender.username as sender_username',
                'sender.first_name as sender_first_name',
                'sender.last_name as sender_last_name',
                'receiver.username as receiver_username',
                'receiver.first_name as receiver_first_name',
                'receiver.last_name as receiver_last_name')->first();

        $responses = $this->container->directMessageResponse->where('message_id', $message->id)
            ->join('users', 'direct_messages_responses.sender_id', '=', 'users.id')
            ->select('direct_messages_responses.*', 'users.username as owner_username', 'users.first_name as owner_first_name', 'users.last_name as owner_last_name')
            ->orderBy('created_at', 'DESC')
            ->get();

        if(!$this->container->flash->getMessage('notySuccess')[0])
            $message->setViewed();

        return $this->render('auth/viewMessage', [
            'message' => $message,
            'responses' => $responses,
        ]);
    }


    public function postComposeMessage() {
        if($this->data() === null) {
            $this->flashNow('error', 'Please fill out the fields!');
            return $this->redirectTo('auth.messages');
        } else {
            $validator = $this->getValidator();

            $data = [
                'message_recipient' => $this->data()->message_recipient,
                'message_subject' => $this->data()->message_subject,
                'message_body' => $this->data()->message_body,
            ];

            $validator->validate([
                'message_recipient|Recipient' => [$data['message_recipient'], 'required|validUsername|notAuthUsername'],
                'message_subject|Subject' => [$data['message_subject'], 'required'],
                'message_body|Message' => [$data['message_body'], 'required'],
            ]);

            if($validator->passes()) {
                $userTo = $this->container->user->where('username', $this->data()->message_recipient)->first();

                if($userTo) {
                    $this->container->directMessage->sendMessage(
                    $userTo->id,
                    $this->container->site->auth->id,
                    $this->data()->message_subject,
                    $this->data()->message_body);

                    $this->flash('notySuccess', 'Your message has been sent!');
                    return $this->redirectTo('auth.messages');
                } else {
                    // We should never reach this point... so I won't add all the posted data here.
                    $this->flash('error', 'We could not find the user you selected.');
                    return $this->redirectTo('auth.messages');
                }
            } else {
                $this->flash('error', 'There were some errors while trying to send your message, please fix them and try again.');

                // These are the posted values
                $this->flash('recipient', $this->data()->message_recipient);
                $this->flash('subject', $this->data()->message_subject);
                $this->flash('body', $this->data()->message_body);

                // These are the errors
                $this->flash('message_recipient_error', $validator->errors()->first('message_recipient'));
                $this->flash('message_subject_error', $validator->errors()->first('message_subject'));
                $this->flash('message_body_error', $validator->errors()->first('message_body'));

                return $this->redirectTo('auth.messages');
            }
        }
    }

    public function postTrashDirectMessages() {
        $user = $this->container->site->auth;
        $rawSelectedMessages = $this->request->getParsedBody()['selectedMessages'];

        $selectedMessages = explode(',', $rawSelectedMessages[0]);

        foreach($selectedMessages as $selectedMessage) {
            $message = $this->container->directMessage->where('id', $selectedMessage)->first();

            if($user->id == $message->receiver_id || $user->id == $message->sender_id) {
                $message->update([
                    'deleted' => true,
                ]);
            }
        }

        if(count($selectedMessages) > 1)
            $this->flash('notySuccess', "You have added those messages to your trash!");
        else
            $this->flash('notySuccess', "You have added that message to your trash!");

        return $this->redirectTo('auth.messages');
    }

    public function postRestoreDirectMessages() {
        $user = $this->container->site->auth;
        $rawSelectedMessages = $this->request->getParsedBody()['selectedMessages'];

        $selectedMessages = explode(',', $rawSelectedMessages[0]);

        foreach($selectedMessages as $selectedMessage) {
            $message = $this->container->directMessage->where('id', $selectedMessage)->first();

            if($user->id === $message->receiver_id) {
                $message->update([
                    'deleted' => false,
                ]);
            }
        }

        if(count($selectedMessages) > 1)
            $this->flash('notySuccess', "You have restored those messages to back to your Inbox!");
        else
            $this->flash('notySuccess', "You have restored that message to back to your Inbox!");

        return $this->redirectTo('auth.messages');
    }

    public function getTrashedMessages() {
        return $this->render('auth/trashedMessages', [
            'messages' => $this->container->site->auth->getDirectMessages(true),
        ]);
    }

    public function postDeleteDirectMessages() {
        $user = $this->container->site->auth;
        $rawSelectedMessages = $this->request->getParsedBody()['selectedMessages'];

        $selectedMessages = explode(',', $rawSelectedMessages[0]);

        foreach($selectedMessages as $selectedMessage) {
            $message = $this->container->directMessage->where('id', $selectedMessage)->first();

            if($user->id === $message->receiver_id) {
                $message->delete();
            }
        }

        if(count($selectedMessages) > 1)
            $this->flash('notySuccess', "You have deleted those messages, forever!");
        else
            $this->flash('notySuccess', "You have deleted that message, forever!");

        return $this->redirectTo('auth.messages');
    }

    public function postDirectMessageResponse() {
        $messageId = $this->request->getAttribute('id');

        $message = $this->container->directMessage->where('id', $messageId)->first();

        if($message) {
            $rawResponse = $this->data()->response;

            $this->container->directMessageResponse->create([
                'message_id' => $messageId,
                'body' => $rawResponse,
                'sender_id' => $this->container->site->auth->id,
            ]);

            $message->setHasReply();

            $this->flash('notySuccess', 'Your reply has been sent!');

            return $this->redirectTo("auth.messages.view", [
                'id' => $message->id,
            ]);
        }

        return $this->redirectTo('auth.messages');
    }

    public function getSentMessages() {
        $sentMessages = $this->container->directMessage
            ->join('users', 'direct_messages.receiver_id', '=', 'users.id')
            ->select('direct_messages.*', 'users.username as sender_username', 'users.first_name as sender_first_name', 'users.last_name as sender_last_name')
            ->where('sender_id', $this->container->site->auth->id)
            ->orderBy('direct_messages.created_at', 'DESC')
            ->get();



        return $this->render('auth/sentMessages', [
            'messages' => $sentMessages,
        ]);
    }
}
