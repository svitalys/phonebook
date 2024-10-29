<?php
/*
отладочная функция
выводит на экран дамп переменной аналогично функциям var_dump() и print_r()
сохраняет предварительное форматирование
преобразует HTML спецсимволы в их сущности
$format - флаг формата
	Если поднят - используется var_dump()
	Иначе - print_r()
*/
function Dump($var, $format=0, $exit=false, $specialchars=true)
{
	ob_start();
	
	if(!$format)
		print_r($var);
	else
		var_dump($var);
	
	$text = ob_get_contents();
	
	ob_end_clean();
	
	if( $specialchars )
		$text = htmlspecialchars($text, ENT_NOQUOTES);
	
	echo '<pre>'.$text.'</pre>';
	
	if( $exit )
		exit;
	
	return $text;
}

/*
Функция безопасного обращения к элементам массива.
Возвращает значение элемента из массива произвольной размерности или NULL, если элемент не найтен
Если функцию вызвать по ссылке - будет возвращена ссылка на элемент массива
$mass - массив значений (принимается по ссылке)
$keys - массив ключей или один ключ
*/
function &getElement(&$mass, $keys=null)
{
	$default = null;
	
	if(is_array($keys))
	{
		foreach($keys as $key)
		{
			if(isset($mass[$key]))
				$mass = &$mass[$key];
			else
				return $default;
		}
		
		return $mass;
	}
	elseif($keys!==null)
	{
		if(isset($mass[$keys]))
			return $mass[$keys];
		else
			return $default;
	}
	else
		return $mass;
}

/*
возвращает значение элемента из глобального массива $_SERVER
Функция с произвольным числом параметров (ключи массива)
	если ни один задан - возвращается число элементов массива
*/
function SERV()
{
	$keys = func_get_args();
	
	if($keys)
		return getElement($_SERVER, $keys);
	else
		return count($_SERVER);
}

/**
 * Функция для замерения времени
 *
 * @return float
 */
function timeMeasure()
{
    list($msec, $sec) = explode(chr(32), microtime());
    return ($sec+$msec);
}

/*
проверяет является ли текущий запрос к серверу AJAX-запросом
*/
function isAjax()
{
	if(SERV('HTTP_X_REQUESTED_WITH')=='XMLHttpRequest')
		return true;
	else
		return false;
}

if (!function_exists('set_magic_quotes_runtime'))
{
    function set_magic_quotes_runtime($new_setting)
    {
	return true;
    }
}

if (!function_exists('get_magic_quotes_gpc'))
{
    function get_magic_quotes_gpc($new_setting)
    {
	return true;
    }
}