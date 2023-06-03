<?php

require_once 'util.php';
require_once('vendor/autoload.php');

/**
 * функция, возвращающая массив невыполненных задач на сегодня
 * @param resource $con ресурс соединения
 *
 * @return array $tasks массив невыполненных задач на сегодня
 */
function getTaskTodayNotCompleted($con)
{
    $sql = 'SELECT * FROM tasks WHERE status = 0 AND due_date = CURRENT_DATE';
    $res = mysqli_query($con, $sql);
    $tasks = mysqli_fetch_all($res, MYSQLI_ASSOC);

    return $tasks;
}

/**
 * функция, возвращающая email пользователя по идентификатору
 * @param resource $con ресурс соединения
 * @param integer $user_id идентификатор пользователя
 *
 * @return string email пользователя по идентификатору
 */
function getUserEmail($con, $user_id)
{
    $sql = 'SELECT email FROM users WHERE id = ?';
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $users = mysqli_fetch_all($res, MYSQLI_ASSOC);
    $user_email = null;

    foreach ($users as $user) {
        $user_email = $user['email'];
    }

    return $user_email;
}

/**
 * функция, отправляющая электронное письмо
 * @param resource $con ресурс соединения
 * @param integer $user_id идентификатор пользователя
 * @param string $message_text текст сообщения
 */
function sendMessage($con, $user_id, $message_text)
{
    $transport = (new Swift_SmtpTransport('smtp.yandex.ru', 465))
        ->setUsername('k3k.kek5@yandex.ru')
        ->setPassword('qqjqurzpcxhrahja')
        ->setEncryption('SSL');

    $message = new Swift_Message('Уведомление от сервиса «Дела в порядке»');
    $message->setTo([getUserEmail($con, $user_id) => getUserName($con, $user_id)]);
    $message->setBody($message_text);
    $message->setFrom('k3k.kek5@yandex.ru', 'Дела в порядке');

    $mailer = new Swift_Mailer($transport);
    $mailer->send($message);
}

/*проверка наличия запланированных на сегодня задач, формирование текста сообщения и отправка письма*/
$tasks = getTaskTodayNotCompleted($con);
$users_id = [];

if (!empty($tasks)) {
    foreach ($tasks as $task) {
        $user_id = $task['user_id'];
        if (!in_array($user_id, $users_id)) {
            $users_id [] = $user_id;
            $message_text = 'Уважаемый ' . getUserName($con, $user_id) . '. У вас запланирована задача ';
            foreach ($tasks as $val) {
                if ($val['user_id'] === $user_id) {
                    $message_text .= $val['name'] . ' на ' . date('d.m.Y') . ', ';
                }
            }
            $message_text = substr($message_text, 0, -2);
            sendMessage($con, $user_id, $message_text);
        }
    }
}
