<?php
    if($argc == 2 && $argv[1] == "--help"){
        echo("Usage: php parse.php [--help]\n");
        exit(0);
    }
    else if($argc != 1){
        exit(10);
    }
    $instruction_counter = 1;
    ini_set("display_errors", "stderr");
    echo("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");

    // flag representing occurrence of header line
    $header = false;

    while($line = fgets(STDIN)){


        // removes comments
        $line = preg_replace("/#.*/","", $line);

        // replaces whitespace with single space character
        $line = preg_replace("/\s+/", " ", $line);

        // removes space from the beginning/end of line
        $line = preg_replace("/^ | $/", "", $line);

        // skips empty lines
        if(preg_match("/^\s*$/", $line)){
            continue;
        }
        // header not found yet
        if(!$header){
            if(preg_match("/^\.IPPCODE21\012?$/", strtoupper($line))){
                $header = true;
                echo("<program language=\"IPPcode21\">\n");
                continue;
            }
            if(preg_match("/^\.IPPCODE20\012?$/", strtoupper($line))){
                $header = true;
                echo("<program language=\"IPPcode20\">\n");
                continue;
            }
            if(preg_match("/^\.IPPCODE19\012?$/", strtoupper($line))){
                $header = true;
                echo("<program language=\"IPPcode19\">\n");
                continue;
            }
            else{
                exit(21);
            }
        }

        // separates the operation code from operands
        $split = explode(" ", trim($line, "\n"));

        // various cases represent specific instructions according to their operation code
        switch(strtoupper($split[0])){

            /*
             * Instruction form: "OPCODE <var>"
             */
            case "DEFVAR":
            case "POPS":
                numberOfOperandsCheck($split, 2);
                echo("\t<instruction order=\"".$instruction_counter."\" opcode=\"".strtoupper($split[0])."\">\n");
                varCheck($split[1], 1);
                echo("\t</instruction>\n");
                $instruction_counter++;
                break;

            /*
             * Instruction form: "OPCODE <var> <symb>"
             */
            case "INT2CHAR":
            case "STRLEN":
            case "MOVE":
            case "TYPE":
            case "NOT":
                numberOfOperandsCheck($split, 3);
                echo("\t<instruction order=\"".$instruction_counter."\" opcode=\"".strtoupper($split[0])."\">\n");
                varCheck($split[1], 1);
                symbCheck($split[2], 2);
                echo("\t</instruction>\n");
                $instruction_counter++;
                break;

            /*
             * Instruction form: "OPCODE"
             */
            case "CREATEFRAME":
            case "PUSHFRAME":
            case "POPFRAME":
            case "RETURN":
            case "BREAK":
                numberOfOperandsCheck($split, 1);
                echo("\t<instruction order=\"".$instruction_counter."\" opcode=\"".strtoupper($split[0])."\">\n");
                echo("\t</instruction>\n");
                $instruction_counter++;
                break;

            /*
             * Instruction form: "OPCODE <label>"
             */
            case "LABEL":
            case "JUMP":
            case "CALL":
                numberOfOperandsCheck($split, 2);
                echo("\t<instruction order=\"".$instruction_counter."\" opcode=\"".strtoupper($split[0])."\">\n");
                labelCheck($split[1], 1);
                echo("\t</instruction>\n");
                $instruction_counter++;
                break;

            /*
             * Instruction form: "OPCODE <symb>"
             */
            case "PUSHS":
            case "WRITE":
            case "EXIT":
            case "DPRINT":
                numberOfOperandsCheck($split, 2);
                echo("\t<instruction order=\"".$instruction_counter."\" opcode=\"".strtoupper($split[0])."\">\n");
                symbCheck($split[1], 1);
                echo("\t</instruction>\n");
                $instruction_counter++;
                break;

            /*
             * Instruction form: "OPCODE <var> <symb1> <symb2>"
             */
            case "ADD":
            case "SUB":
            case "MUL":
            case "IDIV":
            case "LT":
            case "GT":
            case "EQ":
            case "AND":
            case "OR":
            case "STRI2INT":
            case "CONCAT":
            case "GETCHAR":
            case "SETCHAR":
                numberOfOperandsCheck($split, 4);
                echo("\t<instruction order=\"".$instruction_counter."\" opcode=\"".strtoupper($split[0])."\">\n");
                varCheck($split[1], 1);
                symbCheck($split[2], 2);
                symbCheck($split[3], 3);
                echo("\t</instruction>\n");
                $instruction_counter++;
                break;

            /*
             * Instruction form: "OPCODE <label> <symb1> <symb2>"
             */
            case "JUMPIFEQ":
            case "JUMPIFNEQ":
                numberOfOperandsCheck($split, 4);
                echo("\t<instruction order=\"".$instruction_counter."\" opcode=\"".strtoupper($split[0])."\">\n");
                labelCheck($split[1], 1);
                symbCheck($split[2], 2);
                symbCheck($split[3], 3);
                echo("\t</instruction>\n");
                $instruction_counter++;
                break;

            /*
             * Instruction form: "OPCODE <var> <type>"
             */
            case "READ":
                numberOfOperandsCheck($split, 3);
                echo("\t<instruction order=\"".$instruction_counter."\" opcode=\"".strtoupper($split[0])."\">\n");
                varCheck($split[1], 1);
                if(preg_match("/^(int|bool|string)$/", $split[2])){
                    echo("\t\t<arg2 type=\"type\">$split[2]</arg2>\n");
                }
                else{
                    exit(23);
                }
                echo("\t</instruction>\n");
                $instruction_counter++;
                break;

            default:
                exit(22);
        }
    }
    if(!$header){
        exit(21);
    }
    echo("</program>\n");

    /*
     * finds out the type of the constant
     * $string - string literal representing constant
     */
    function typeCheck($string){
        if(preg_match("/^int@(\+|-|)?[0-9]+$/", $string)){
            return "int";
        }
        else if(preg_match("/^bool@(false|true)$/", $string)){
            return "bool";
        }
        // allows UTF-8 printable characters, except of whitespaces, "#" and "\"
        // allows \000 - \999 escape sequences
        else if(preg_match("/^string@([^\\000-\\040\\043\\134]|(\\\\(?=([0-9][0-9][0-9]))))*$/", $string)){
            return "string";
        }
        else if($string == "nil@nil"){
            return "nil";
        }
        return "wrong";
    }

    /*
     * checks if the variable operand is lexically correct
     * prints the corresponding XML element
     * $string - string literal representing the variable
     * $position - operand order (needed for printing XML element <arg>)
     */
    function varCheck($string, $position){
        if(preg_match("/^(LF|GF|TF)@[a-zA-Z_\-$&%*!?][a-zA-Z_\-$&%*!?0-9]*$/", $string)){
            $string = xmlEscapes($string);
            echo("\t\t<arg".$position." type=\"var\">$string</arg".$position.">\n");
        }
        else{
            exit(23);
        }
    }

    /*
     * checks if the variable/constant operand is lexically correct
     * prints the corresponding XML element
     * $string - string literal representing the variable or constant
     * $position - operand order (needed for printing XML element <arg>)
     */
    function symbCheck($string, $position){

        // operand represents variable
        if(preg_match("/^(LF|GF|TF)@[a-zA-Z_\-$&%*!?][a-zA-Z_\-$&%*!?0-9]*$/", $string)){
            $string = xmlEscapes($string);
            echo("\t\t<arg".$position." type=\"var\">$string</arg".$position.">\n");
        }
        // operand represents constant
        else if(typeCheck($string) != "wrong"){
            $string = xmlEscapes($string);
            $type = typeCheck($string);
            $string = str_replace($type."@", "", $string);
            echo("\t\t<arg".$position." type=\"".$type."\">$string</arg".$position.">\n");
        }
        else{
            exit(23);
        }
    }

    /*
     * checks if the label operand is lexically correct
     * prints the corresponding XML element
     * $string - string literal representing the label
     * $position operand order (needed for printing XML element <arg>)
     */
    function labelCheck($string, $position){
        if(preg_match("/^[a-zA-Z_\-$&%*!?][a-zA-Z_\-$&%*!?0-9]*$/", $string)){
            echo("\t\t<arg".$position." type=\"label\">$string</arg".$position.">\n");
        }
        else{
            exit(23);
        }
    }

    /*
     * checks the number of instruction operands
     * $array - array of string literals, representing split IPPcode21 instruction by " " delimeter
     * $number - represents the correct number of operands
     */
    function numberOfOperandsCheck($array, $number){
        if(count($array) != $number){
            exit(23);
        }
    }

    /*
     * replaces special XML characters with XML escape sequences
     * $string - string literal to be changed (XML special characters -> escape sequences)
     */
    function xmlEscapes($string){
        $string = preg_replace("/&/", "&amp;", $string);
        $string = preg_replace("/</", "&lt;", $string);
        $string = preg_replace("/>/", "&gt;", $string);
        $string = preg_replace("/\"/", "&quot;", $string);
        $string = preg_replace("/'/", "&apos;", $string);
        return $string;
    }