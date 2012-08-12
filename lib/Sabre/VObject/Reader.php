<?php

namespace Sabre\VObject;

/**
 * VCALENDAR/VCARD reader
 *
 * This class reads the vobject file, and returns a full element tree.
 *
 * TODO: this class currently completely works 'statically'. This is pointless,
 * and defeats OOP principals. Needs refactoring in a future version.
 *
 * @copyright Copyright (C) 2007-2012 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class Reader {

    /**
     * Parses the file and returns the top component
     *
     * @param string $data
     * @return Element
     */
    static function read($data) {

        // Normalizing newlines
        $data = str_replace(array("\r","\n\n"), array("\n","\n"), $data);

        $lines = explode("\n", $data);

        // Unfolding lines
        $lines2 = array();
        foreach($lines as $line) {

            // Skipping empty lines
            if (!$line) continue;

            if ($line[0]===" " || $line[0]==="\t") {
                $lines2[count($lines2)-1].=substr($line,1);
            } else {
                $lines2[] = $line;
            }

        }

        unset($lines);

        reset($lines2);

        return self::readLine($lines2);

    }

    /**
     * Reads and parses a single line.
     *
     * This method receives the full array of lines. The array pointer is used
     * to traverse.
     *
     * @param array $lines
     * @return Element
     */
    static private function readLine(&$lines) {

        $line = current($lines);
        $lineNr = key($lines);
        next($lines);

        // Components
        if (stripos($line,"BEGIN:")===0) {

            $componentName = strtoupper(substr($line,6));
            $obj = Component::create($componentName);

            $nextLine = current($lines);

            while(stripos($nextLine,"END:")!==0) {

                $obj->add(self::readLine($lines));

                $nextLine = current($lines);

                if ($nextLine===false)
                    throw new ParseException('Invalid VObject. Document ended prematurely.');

            }

            // Checking component name of the 'END:' line.
            if (substr($nextLine,4)!==$obj->name) {
                throw new ParseException('Invalid VObject, expected: "END:' . $obj->name . '" got: "' . $nextLine . '"');
            }
            next($lines);

            return $obj;

        }

        // Properties
        //$result = preg_match('/(?P<name>[A-Z0-9-]+)(?:;(?P<parameters>^(?<!:):))(.*)$/',$line,$matches);


        $token = '[A-Z0-9-\.]+';
        $parameters = "(?:;(?P<parameters>([^:^\"]|\"([^\"]*)\")*))?";
        $regex = "/^(?P<name>$token)$parameters:(?P<value>.*)$/i";

        $result = preg_match($regex,$line,$matches);

        if (!$result) {
            throw new ParseException('Invalid VObject, line ' . ($lineNr+1) . ' did not follow the icalendar/vcard format');
        }

        $propertyName = strtoupper($matches['name']);
        $propertyValue = Property::stripSlashes($matches['value']);
        /* Maybe it's better to just remove '|;|,' from the regex below?
         * $propertyValue = preg_replace_callback('#(\\\\(\\\\|N|n|;|,))#',function($matches) {
            if ($matches[2]==='n' || $matches[2]==='N') {
                return "\n";
            } else {
                return $matches[2];
            }
        }, $matches['value']);*/

        $obj = Property::create($propertyName, $propertyValue);

        if ($matches['parameters']) {

            foreach(self::readParameters($matches['parameters']) as $param) {
                $obj->add($param);
            }

        }

        return $obj;


    }

    /**
     * Reads a parameter list from a property
     *
     * This method returns an array of Parameter
     *
     * @param string $parameters
     * @return array
     */
    static private function readParameters($parameters) {

        $token = '[A-Z0-9-]+';

        $paramValue = '(?P<paramValue>[^\"^;]*|"[^"]*")';

        $regex = "/(?<=^|;)(?P<paramName>$token)(=$paramValue(?=$|;))?/i";
        preg_match_all($regex, $parameters, $matches,  PREG_SET_ORDER);

        $params = array();
        foreach($matches as $match) {

            $value = isset($match['paramValue'])?$match['paramValue']:null;

            if (isset($value[0])) {
                // Stripping quotes, if needed
                if ($value[0] === '"') $value = substr($value,1,strlen($value)-2);
            } else {
                $value = '';
            }

            $value = preg_replace_callback('#(\\\\(\\\\|N|n|;|,))#',function($matches) {
                if ($matches[2]==='n' || $matches[2]==='N') {
                    return "\n";
                } else {
                    return $matches[2];
                }
            }, $value);

            $params[] = new Parameter($match['paramName'], $value);

        }

        return $params;

    }


}
