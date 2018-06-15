<?php

class getVkMessages
{
    /*Для работы скрипта необходимо вставить токен группы и id_сообщества*/

    //токен группы
    private $token = "токен группы";
    //id_сообщества
    private $group_id = "id сообщества";
    //искомая дата
    private $time_start;
    private $time_end;
    //кол-во сообщений, которое можно получить за один запрос (max=200)
    private $count = 200;
    //смещение, необходимое для выборки определенного подмножества сообщений
    private $offset = 0;
    //массив полученых сообщений
    private $result_messages = [];
    //массив полученой истории
    private $result_history = [];

    private function validPost()
    {
        if (empty($_POST["day"]) || empty($_POST["month"]) || empty($_POST["year"]))
        {
            die("Заполните все поля");
        }
        elseif(!filter_var($_POST["day"], FILTER_VALIDATE_INT)|| 
                !filter_var($_POST["month"], FILTER_VALIDATE_INT)||
                !filter_var($_POST["year"], FILTER_VALIDATE_INT))
        {
            die("Дата должна быть в числовом значении");
        }
    }

    /*Функция получение даты в unixtime*/

    private function getDate()
    {
        $this -> time_start = mktime(0,0,0,$_POST["month"],$_POST["day"],$_POST["year"]);
        $this -> time_end = mktime(23,59,59,$_POST["month"],$_POST["day"],$_POST["year"]);
        return;
    }

    /*Функция доступа к API*/

    private function getAPI($string)
    {
        @$api = (file_get_contents($string))
        or die ("Файл API недоступен. Попробуйте позже или сообщите администратору");
        $result = json_decode($api);
        $result = $result -> response -> items;
        return $result;
    }

    /*вывод результата*/
    private function returnView ($count_answer_time)
    {
        if (!$count_answer_time) {die("Ответов в этот день не обнаружено");}
        echo "<p>Среднее время ответа "
            .floor( ( array_sum ($count_answer_time) / count ($count_answer_time) ) / 60 ).
            " минут </p>";
        echo "<p>Минимальное время ответа "
            .floor( min($count_answer_time) /60 ).
            " минут</p>";
        echo "<p>Максимальное время ответа "
            .floor ( max($count_answer_time) /60 ).
            " минут</p>";
    }

    /*вывод ссылок на диалоги с временем ответа >15 минут*/
    private function returnLinks ($array_links)
    {
        echo "<div><p>Ссылки на диалоги с временем ответа >15 минут:</p>";
        for ($i = 0; $i < count($array_links); $i++)
        {
            echo "<p><a href='".$array_links[$i]."'>$array_links[$i]</a></p>";
        }
        echo "</div>";
    }

    /*
     * Функция поиска всех сообщений до определенной даты
     */

    private function runSearch()
    {
        $this -> validPost();
        $this -> getDate();
        $time_offset = time() - $this->time_start;
        $messages = $this->getAPI("https://api.vk.com/method/messages.get?offset=$this->offset&rev=1&count=$this->count&time_offset=$time_offset&v=5.52&access_token=$this->token");

        if (!$messages){die("Данных по этому дню не обнаружено");}

        foreach ($messages as $val)
        {
            if ($val->date > $this->time_start AND $val->date < $this->time_end)
            {
                $this->result_messages[] = $val;
            }
        }
        if (count($messages) == $this->count)
        {
            return $this->runSearch();
        }
        if (!$this->result_messages){die("Сообщений по искомой дате не найдено.");}
        return $this->result_messages;
    }

    /*
     * Функция поиска диалогов сообщений
     * принимает результат выполнения функции runSearch()
     * $uniq_user_id - массив уникальных user_id
     */

    private function getHistory($array_messages)
    {
        $uniq_user_id = [];
        for ($i = 0; $i < count ($array_messages); $i++)
        {
            if(!in_array($array_messages[$i]->user_id,$uniq_user_id)){
                array_push($uniq_user_id,$array_messages[$i]->user_id);
                $history = $this->getAPI("https://api.vk.com/method/messages.getHistory?user_id={$array_messages[$i]->user_id}&rev=1&count=200&v=5.74&access_token=$this->token");
                foreach ($history as $val)
                {
                    $this->result_history[] = $val;
                }
            }
        }
        return $this->result_history;
    }

    /*
     * Функция вывода минимального, максимального и среднего времени ответа
     * с ссылками на диалоги с временем ответа >15 минут
     * На вход принимает результат выполнения функции getHistory()
     * $income_time время входящего сообщения
     * $out_time время исходящего сообщения
     * $count_answer_time массив времени ответа
     * $array_links массив ссылок на диалоги с временем ответа >15 минут
     */

    private function returnHistory($history)
    {
        $income_time = 0;
        $out_time = 0;
        $count_answer_time = [];
        $array_links=[];

        print_r($this->time_start);
        print_r($this->time_end);

        for ($i = 0; $i < count($history); $i++)
        {
            if ($this->time_start < $history[$i]->date AND $history[$i]->date < $this->time_end)
            {
                if ($history[$i]->out == 0)
                {
                    $income_time = $history[$i]->date;
                }
                else
                {
                    if($income_time == 0)
                    {
                        continue;
                    }
                    $out_time = $history[$i]->date - $income_time;
                    array_push($count_answer_time,$out_time);
                    $income_time = 0;
                    $out_time = 0;
                    if ($out_time > 900)
                    {
                        $link = "https://vk.com/gim{$this->group_id}?sel={$history[$i]->user_id}";
                        if (!in_array($link,$array_links))
                        {
                            array_push($array_links,$link);
                        }
                    }
                }
            }
        }
        $this->returnView($count_answer_time);

        if ($array_links)
        {
            $this->returnLinks($array_links);
        }
        return;
    }

    public function runThis()
    {
        $this->returnHistory($this->getHistory($this->runSearch()));
        return;
    }
}
