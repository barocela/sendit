<?php
/**
 * CSV to XML
 *
 * A simple, speedy class to convert the CSV files to XML
 *
 * Features
 * 
 *	-	convert CSV file to XML
 *	-	Data sanitization
 *	-	PHP5 and PHP4 compatibility
 *
 * License
 * 
 * Copyright (c) 2011, Amit YAdav <amityadav@amityadav.name>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without 
 * modification, are permitted provided that the following conditions are met:
 * 
 *	-	Redistributions of source code must retain the above copyright notice, 
 *		this list of conditions and the following disclaimer.
 *
 *	-	Redistributions in binary form must reproduce the above copyright 
 *		notice, this list of conditions and the following disclaimer in the 
 *		documentation and/or other materials provided with the distribution.
 *
 *	-	Neither the name of Made Media Ltd. nor the names of its contributors 
 *		may be used to endorse or promote products derived from this 
 *		software without specific prior written permission.
 *
 *	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS 
 *	IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, 
 *	THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR 
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR 
 *	CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, 
 *	EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, 
 *	PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR 
 *	PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF 
 *	LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING 
 *	NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS 
 *	SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * Limitations
 * 
 * The class converts the CSV file to XML, but not XSL to XML.
 * In order to convert the XLS files you need to save them as CSV first and then use this class
 *
 *
 * @category	Utilities
 * @package		CSV2XML
 * @author		Amit YAdav <amityadav@amityadav.name>
 * @copyright	2011 Amit Yadav
 * @version		0.1
 * @website		http://amityadav.name
 */

Class csv2xml{
	var $rootNode = "root";
	var $recurringNode = "row_item";
	var $CSVFileName = "";

	//Function to set the root node for the XML
	function setRootNode($rootNode){
		$this->rootNode = $rootNode;
	}

	//Function to set the item node for the XML
	function setRecurringNode($recurringNode){
		$this->recurringNode = $recurringNode;
	}


	//Function to set the finame of the CSV file
	function setCSVFile($fileName){
		$this->CSVFileName = $fileName;
	}


	//Function to read and convert the CSV file to an XML file
	//The name of the CSV file will be the name of the XMl file with extension ".xml"
	function convertCSV2XML(){
		$row = 1;
		$columns = array();
		$num = 0;
		$isNumSet = false;

		$str = "<?xml version='1.0' encoding='ISO-8859-1'?>\n<" . $this->rootNode . ">\n";
			
		if (($handle = fopen($this->CSVFileName, "r")) !== FALSE) {
			while (($data = fgetcsv($handle, 1024, ",")) !== FALSE) {
				$fieldCount = count($data); 
				if($row != 1)
					$str .= "<" . $this->recurringNode . ">\n";

				for ($c=0; $c < $fieldCount; $c++) {	
					if($row == 1 && $data[$c] != '' && !$isNumSet){
						$columns [] = preg_replace(array('/\./', '/\?/'), '', preg_replace(array('/\(/', '/\)/', '/ /', '/\//', '/__/'), '_', trim($data[$c])));
						$num++;
					}else{
						if($columns[$c] != '')
							$str .= "<{$columns[$c]}>" . preg_replace('/\&/', 'and', trim($data[$c])) . "</{$columns[$c]}>\n";
					}
				}
				
				if($row != 1)
					$str .= "</" .  $this->recurringNode . ">\n\n";

				$row++;
				$isNumSet = true;
			}
			fclose($handle);
		}
		
		$str .= '</' . $this->rootNode . '>';
		

		//Create & write the data to the XML file
		$filename = str_replace("csv" , "xml", strtolower($this->CSVFileName));

		  if (!$handle = fopen($filename, 'w+')) {
			 echo "Cannot open file ($filename)";
			 exit;
			}

		//Write the data to the XML file
		if (fwrite($handle, $str) === FALSE) {
			echo "Cannot write to file ($filename)";
			exit;
		}else{
			echo "File converted successfully.<br/> Filename: <u>\"{$filename}\"</u>";
		}

		fclose($handle);
	}//Function end

}//End of th class
?> 
