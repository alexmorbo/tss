<?php

/**
 * Обертка над курлом
 */
class Curler
{
    /**
     * @var array
     */
    protected static $_logData = [];


    /**
     * Добавляет логи
     *
     * @param string $url
     * @param string $logRow
     */
    public static function appendLog($url, $logRow)
    {
        self::$_logData[sha1($url)][] = $logRow;
    }


    /**
     * Возвращает логи
     *
     * @param string $url
     *
     * @return array
     */
    public static function getLogs($url)
    {
        return isset(self::$_logData[sha1($url)])
            ? self::$_logData[sha1($url)]
            : [];
    }


    /**
     * @var array
     */
    protected static $_logInfo = [];


    /**
     * Добавляет инфу
     *
     * @param string $url
     * @param array  $info
     */
    public static function addInfo($url, $info)
    {
        self::$_logInfo[sha1($url)][] = $info;
    }


    /**
     * Возвращает инфу
     *
     * @param string $url
     *
     * @return array
     */
    public static function getInfo($url)
    {
        return isset(self::$_logInfo[sha1($url)])
            ? self::$_logInfo[sha1($url)]
            : [];
    }


    /**
     * @param string $url      Адрес страницы
     * @param array  $postData Массив с пост данными
     * @param int    $timeout  Таймаут
     * @param array  $opts     Доп параметры
     * @param int    $loop     Редиректы
     *
     * @return bool|mixed
     */
    public static function getPage($url, $postData = [], $timeout = 15,
        $opts = [], $loop = 0)
    {
        $notifyData = [
            '_url' => $url,
            '_postData' => json_encode($postData),
            '_timeout' => $timeout
        ];
        // Проверка на вечный редирект
        if ($loop > 10) {
            $notifyData['errorText'] = 'Infinite redirect loop!';
            $emailed = self::errorNotify($notifyData);
            foreach ($emailed as $email => $status) {
                self::appendLog(
                    $url, 'Admin notify (' . $email . ') is ' . $status
                );
            }

            return false;
        }

        // Получаем данные
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if (count($postData)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        foreach ($opts as $k => $v) {
            curl_setopt($ch, $k, $v);
        }

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        $errorCode = curl_errno($ch);
        $errorText = curl_error($ch);
        curl_close($ch);

        self::addInfo($url, $info);

        // Если ошибка
        if ($errorCode) {
            $notifyData['errorCode'] = $errorCode;
            $notifyData['errorText'] = $errorText;

            $emailed = self::errorNotify($notifyData);
            foreach ($emailed as $email => $status) {
                self::appendLog(
                    $url, 'Admin notify (' . $email . ') is ' . $status
                );
            }
        } elseif ($info['http_code'] != 200 && ! $info['redirect_url']) {
            $notifyData['errorCode'] = $info['http_code'];
            $notifyData['errorText'] = 'HTTP ERROR';

            $emailed = self::errorNotify($notifyData);
            foreach ($emailed as $email => $status) {
                self::appendLog(
                    $url, 'Admin notify (' . $email . ') is ' . $status
                );
            }
        } elseif ($info['redirect_url']) {
            // Перенаправление?

            return self::getPage(
                $info['redirect_url'], $postData, $timeout, $opts, $loop+1
            );
        }

        return $result;
    }


    /**
     * Уведомляет администраторов о проблеме
     *
     * @param array $meta
     *
     * @return array
     */
    public static function errorNotify($meta)
    {
        return [];
    }
}