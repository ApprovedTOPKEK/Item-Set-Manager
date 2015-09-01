<?php

/**
 * Class Template
 *
 * You don't even want me to get started on this. Reaaaally messy templating system built with time, only because I wanted to avoid using <?php ?> tags in htmlcode.
 *
 * Ok, basically, this is how it works:
 * - When preparing, the template searches for everything between curly braces.
 * - The stuff between curly braces is then tested against a few regexes, each having their own callback.
 * - If there is a match, call the callback, which returns the replacement.
 * - Repeat until nothing between curly braces is found.
 */
class Template
{

	private $cleanTemplate;
	private $rawTemplate; //bad name since it is only raw before loading... maybe just call it templateContent or something
	private $varNameMap = array();

	private $matchCallbacks = array(); //WHEN ADDING CALLBACKS, THE ORDER IS IMPORTANT; OTHERWISE THE RESULT COULD BE WEIRD.
	private function replaceNext(&$where){
		$m = array();
		if(preg_match("#\{.+}.*?#iUs", $where, $m)) {
			foreach ($this->matchCallbacks as $arr) {
				if (preg_match("#^".$arr[0]."#iUs", $m[0])) {
					$where = preg_replace_callback("#".$arr[0]."#iUs", $arr[1], $where, 1);

					if (count($arr) > 2) $arr[2]();
					return true;
				}
			}
		}
		return false;
	}

	public function setVar($name, $var)
	{
		$this->varNameMap[$name] = $var;
	}

	public function clean(){
		$this->rawTemplate = $this->cleanTemplate;
	}

	public function __construct($file, $fromFile = true)
	{
		$this->rawTemplate = $fromFile?file_get_contents($file):$file;
		$this->cleanTemplate = $this->rawTemplate;
		//Add the regex-callback-methods
		$loop = function($matches){
			$loopResult = "";
			$index = 0;
			$t = count($this->varNameMap[$matches[3]]) - 1;
			$this->setVar("maxIndex".$matches[1], $t);

			foreach($this->varNameMap[$matches[3]] as $val){
				$this->setVar("index" . $matches[1], $index);
				$this->setVar($matches[2], $val);
				$loopContentCopy = $matches[4];
				while ($this->replaceNext($loopContentCopy) == true)
					continue;

				$loopResult .= $loopContentCopy;
				$index++;
			}

			unset($this->varNameMap[$matches[2]]);
			unset($this->varNameMap["index" . $matches[1]]);
			unset($this->varNameMap["maxIndex" . $matches[1]]);
			return $loopResult;
		};
		$condition = function ($matches) {
			$result = false;
			switch ($matches[3]) {
				case "<":$result = $this->varNameMap[$matches[2]] < $this->varNameMap[$matches[4]];break;
				case ">":$result = $this->varNameMap[$matches[2]] > $this->varNameMap[$matches[4]];break;
				case ">=":$result = $this->varNameMap[$matches[2]] >= $this->varNameMap[$matches[4]];break;
				case "<=":$result = $this->varNameMap[$matches[2]] <= $this->varNameMap[$matches[4]];break;
				case "==":$result = $this->varNameMap[$matches[2]] == $this->varNameMap[$matches[4]];break;
				case "!=":$result = $this->varNameMap[$matches[2]] != $this->varNameMap[$matches[4]];break;
			}
			return $result ? $matches[5] : "";
		};
		$propertyString = function ($matches){
			$prop = $matches[2];
			return $this->varNameMap[$matches[1]]->$prop;
		};
		$propertyReal = function ($matches) {
			$prop = $this->varNameMap[$matches[2]];
			return $this->varNameMap[$matches[1]]->$prop;
		};
		$staticProperty = function ($matches){
			$class = new ReflectionClass($matches[1]);
			return $class->getStaticPropertyValue($matches[2]);
		};
		$simpleVar = function ($matches){
			return isset($this->varNameMap[$matches[1]])?$this->varNameMap[$matches[1]]:"{Error at: ".$matches[1].".}";
		};
		$mathOperations = function ($matches){
			$operand1 = is_numeric($matches[1])?$matches[1]:$this->varNameMap[$matches[1]];
			$operand2 = is_numeric($matches[3]) ? $matches[3] : $this->varNameMap[$matches[3]];
			switch($matches[2]){
				case "+": return $operand1 + $operand2;
				case "-": return $operand1 - $operand2;
				case "/": return $operand1 / $operand2;
				case "%": return $operand1 % $operand2;
				case "*": return $operand1 * $operand2;
			}
		};
		$functionCall = function ($matches){
			return $this->varNameMap[$matches[1]]();
		};
		$objectFunctionCall = function ($matches){
			$fun = $matches[2];
			return $this->varNameMap[$matches[1]]->$fun();
		};
		$include = function($matches){
			$file = $matches[1];
			return file_get_contents($file);
		};

		$setvarObj = function ($matches){
			$prop = $matches[3];
			$this->setVar($matches[1], $this->varNameMap[$matches[2]]->$prop);
			return "";
		};

		$setvarObjReal = function ($matches) {
			$prop = $this->varNameMap[$matches[3]];
			$this->setVar($matches[1], $this->varNameMap[$matches[2]]->$prop);
			return "";
		};

		$fun = function ($matches) {
			$res = call_user_func($matches[1], $this->varNameMap[$matches[2]]);
			return $res?$res:"";
		};

		$setvarObjfun = function ($matches) use ($fun) {
			$this->setVar($matches[1], $fun(array("", $matches[2], $matches[3])));
		};
		//omg why do you even read this <.<

		array_push($this->matchCallbacks, array('\{\s?for(\w+)\s(\w+)\sin\s(\w+)\s?\}(.+?)\{end\g1\s?}', $loop)); /*"#\{\s?for(\w+)\s(\w+)\sin\s(\w+)\s?\}(.+?)\{end\g1\s?}|\{\s?for(\w+)\s(\w+)\s?\=([0-9]+)\s?\;\s?(\w+)\s?(<|>|>=|<=|==|!=)\s?(\w+)\s?\;\s?(\+\+|\-\-|\|)(\w+)(\+\+|\-\-|\|)\s?\}(.+?)\{end\g1\s?}#iUs"*/
		array_push($this->matchCallbacks, array('\{\s?if(\w+)\s(\w+)\s?(<|>|>=|<=|==|!=)\s?(\w+)\s?\}(.+?)\{end\g1\s?}', $condition));
		array_push($this->matchCallbacks, array('\{\s?(\w+)\s?\(\s?(\w+)\s?\)\s?}', $fun));
		//array_push($this->matchCallbacks, array('\{\s?\$\s?(\w+)\s?\(\s?(\w+)\s?\)\s?}', $realfun));
		array_push($this->matchCallbacks, array('\{\s?(\w+)\s?\=\s?(\w+)\s?\(\s?(\w+)\s?\)\s?}', $setvarObjfun));
		array_push($this->matchCallbacks, array('\{\s?(\w+)\s?\=\s?(\w+)\s?->\$\s?(\w+)\s?}', $setvarObjReal)); //Allows {test = myObj->anotherObj} to later allow {anotherObj->var}
		array_push($this->matchCallbacks, array('\{\s?(\w+)\s?\=\s?(\w+)\s?->\s?(\w+)\s?}', $setvarObj));
		array_push($this->matchCallbacks, array('\{\s?(\w+)\s?->\$\s?(\w+)\s?}', $propertyReal));
		array_push($this->matchCallbacks, array('\{\s?(\w+)\s?->\s?(\w+)\s?}', $propertyString));
		array_push($this->matchCallbacks, array('\{\s?(\w+)\s?::\s?(\w+)\s?}', $staticProperty)); //shitshitshit this whole stuff is horrible
		array_push($this->matchCallbacks, array('\{\s?(\w+)\s?(\+|\-|\/|\%|\*)\s?(\w+)\s?}', $mathOperations));
		array_push($this->matchCallbacks, array('\{\s?(\w+)\s?\(\s?\)\s?}', $functionCall));
		array_push($this->matchCallbacks, array('\{\s?(\w+)\s?->\s?(\w+)\s?\(\s?\)\s?}', $objectFunctionCall));
		array_push($this->matchCallbacks, array('\{\s?include\s(.+)\s?}', $include));
		array_push($this->matchCallbacks, array('\{\s?(\w+)\s?}', $simpleVar));

		$this->setVar("true", true);
		$this->setVar("false", false);
		$this->setVar("0", 0);
		$this->setVar("-1", -1);
		$this->setVar("1", 1);
	}

	//Bad function name. Find something else
	public function prepare(){
		while($this->replaceNext($this->rawTemplate))
			continue; //Unnecessary, but makes it easier to read/understand
	}

	public function printTemplate(){
		return $this->rawTemplate;
	}
}

?>