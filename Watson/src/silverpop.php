<?php

namespace Watson;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SimpleXMLElement;
/**
* 
*/
class silverpop
{
    //Login API
    public function login($username, $password) {
        
        $this->url = 'http://api2.ibmmarketingcloud.com/XMLAPI';
        $this->body = '<Envelope><Body><Login><USERNAME>'.$username.'</USERNAME><PASSWORD>'.$password.'</PASSWORD></Login></Body></Envelope>';
        $client = new Client();
        try {
        $res = $client->post($this->url, [
        'headers' => [
            'content-type'     => 'text/xml; charset=UTF8',
        ],
        'body' => $this->body
        ]);
        $response = new SimpleXMLElement ($res->getBody());
        $result = [];
        $result['status'] = (string) $response->Body->RESULT->SUCCESS;
        if ($response instanceof \SimpleXMLElement && (string) $response->Body->RESULT->SUCCESS == 'true') {
            $result['sessionId'] = isset($response->Body->RESULT->SESSIONID) ? (string) $response->Body->RESULT->SESSIONID: null;
        } elseif ($response instanceof \SimpleXMLElement && (string) $response->Body->RESULT->SUCCESS == 'false') {
            $xmlParserCreate = xml_parser_create();
            xml_parse_into_struct($xmlParserCreate, $res->getBody(), $vals, $index);
            xml_parser_free($xmlParserCreate);
            $result['faultString'] = $vals[$index['FAULTSTRING'][0]]['value'];
        }
        }  catch (GuzzleException $e) {
            throw new Exception('Login failed: ' . $e->getMessage());
        }
        return $result;
    }

    //Logout API
    public function logout($jsessionID) {
        $this->url = "http://api2.ibmmarketingcloud.com/XMLAPI;jsessionid=".$jsessionID;
        $this->body = '<Envelope><Body><Logout/></Body></Envelope>';
        $client = new Client();
        try {
        $res = $client->post($this->url, [
        'headers' => [
            'content-type'     => 'text/xml; charset=UTF8',
        ],
        'body' => $this->body
        ]);
        $response = new SimpleXMLElement ($res->getBody());
        $result[] = (string) $response->Body->RESULT->SUCCESS;
        }  catch (GuzzleException $e) {
            throw new Exception('Login failed: ' . $e->getMessage());
        }
        return $result;
    }

    //Job Status
    public function jobStatus($jobId, $sessionId) {
        $this->url = "http://api2.ibmmarketingcloud.com/XMLAPI;jsessionid=".$sessionId;

        $xml =  '<Envelope>
                    <Body>
                        <GetJobStatus>
                            <JOB_ID>'.$jobId.'</JOB_ID>
                        </GetJobStatus>
                    </Body>
                </Envelope>';
        $client = new Client();
        try {
            $res = $client->post($this->url, [
            'headers' => [
                'content-type'     => 'text/xml; charset=UTF8',
            ],
            'body' => $xml
            ]);
            $response = new SimpleXMLElement ($res->getBody());
            $jobStatus = (string) $response->Body->RESULT->JOB_STATUS;
            if ($jobStatus != 'COMPLETE') {
                return $this->jobStatus($jobId, $sessionId);
            } else {
                $result['jobStatus'] = $jobStatus;
            }
            if ($response instanceof \SimpleXMLElement && (string) $response->Body->RESULT->SUCCESS == 'false') {
                $xmlParserCreate = xml_parser_create();
                xml_parse_into_struct($xmlParserCreate, $res->getBody(), $vals, $index);
                xml_parser_free($xmlParserCreate);
                $result['faultString'] = $vals[$index['FAULTSTRING'][0]]['value'];
            }
        }   catch (GuzzleException $e) {
            throw new Exception('Unable to export list: ' . $e->getMessage());
        }
        return $result;
    }

    //Preview Mailing API
    public function previewMailing($mailId, $jsessionID) {
        $this->url = "http://api2.ibmmarketingcloud.com/XMLAPI;jsessionid=".$jsessionID;
        $this->body = '<Envelope><Body><PreviewMailing><MailingId>'.$mailId.'</MailingId></PreviewMailing></Body></Envelope>';
        $client = new Client();
        try {
        $res = $client->post($this->url, [
        'headers' => [
            'content-type'     => 'text/xml; charset=UTF8',
        ],
        'body' => $this->body
        ]);
        $response = new SimpleXMLElement ($res->getBody());
        $result['status'] = (string) $response->Body->RESULT->SUCCESS;
        if ($result['status'] == 'TRUE') {   
            $result['HTMLBody'] = (string) $response->Body->RESULT->HTMLBody;
        }
        }  catch (GuzzleException $e) {
            throw new Exception('previewMailing failed: ' . $e->getMessage());
        }
        return $result;
    }

    //Create sendMailing API
    public function sendMailing($templateId, $email) {
        

        $loginResult = $this->login();

        if (isset($loginResult['sessionId'])) {

            $xml =  '<Envelope>
                        <Body>
                        <SendMailing>
                        <MailingId>'.$templateId.'</MailingId>
                        <RecipientEmail>'.$email.'</RecipientEmail>
                        </SendMailing>
                        </Body>
                    </Envelope>';
            $this->url = "http://api2.ibmmarketingcloud.com/XMLAPI;jsessionid=".$loginResult['sessionId'];
            $client = new Client();
            try {
                $res = $client->post($this->url, [
                'headers' => [
                    'content-type'     => 'text/xml; charset=UTF8',
                ],
                'body' => $xml
                ]);
                $response = new SimpleXMLElement ($res->getBody());
                $result['status'] = (string) $response->Body->RESULT->SUCCESS;
                if ($response instanceof \SimpleXMLElement && (string) $response->Body->RESULT->SUCCESS == 'false') {
                    $xmlParserCreate = xml_parser_create();
                    xml_parse_into_struct($xmlParserCreate, $res->getBody(), $vals, $index);
                    xml_parser_free($xmlParserCreate);
                    $result['faultString'] = $vals[$index['FAULTSTRING'][0]]['value'];
                }
            }   catch (GuzzleException $e) {
                throw new Exception('Unable to send mailing: ' . $e->getMessage());
            }
            $logoutResult = $this->logout($loginResult['sessionId']);
            return $result;
        } else {
            return $loginResult;
        } 
    }

    //Create scheduleMailing API
    public function scheduleMailing($templateId, $queryId, $mailingName, $subject) {
        
        $loginResult = $this->login();

        if (isset($loginResult['sessionId'])) {
            if ($queryId == '') {
                $queryId = env("SILVERPOP_TEST_DB_LIST_ID");
            }
            if ($subject =='') {
                $subjectData = '<SUBJECT></SUBJECT>';
            } else {
                $subjectData = '<SUBJECT>'.$subject.'</SUBJECT>';
            }
            $xml =  '<Envelope><Body>
                    <ScheduleMailing>
                    <TEMPLATE_ID>'.$templateId.'</TEMPLATE_ID>
                    <LIST_ID>'.$queryId.'</LIST_ID>
                    <MAILING_NAME>'.$mailingName.'</MAILING_NAME>
                    <SEND_HTML/>
                    <SEND_AOL/>
                    <SEND_TEXT/>'
                    .$subjectData.
                    '<FROM_NAME></FROM_NAME>
                    <FROM_ADDRESS></FROM_ADDRESS>
                    <REPLY_TO></REPLY_TO>
                    <VISIBILITY>1</VISIBILITY>
                    <SCHEDULED></SCHEDULED>
                    '.env('SILVERPOP_CUSTOM_OPTOUT').'
                    </ScheduleMailing>
                    </Body></Envelope>';
            $this->url = "http://api2.ibmmarketingcloud.com/XMLAPI;jsessionid=".$loginResult['sessionId'];
            $client = new Client();
            try {
                $res = $client->post($this->url, [
                'headers' => [
                    'content-type'     => 'text/xml; charset=UTF8',
                ],
                'body' => $xml
                ]);
                $response = new SimpleXMLElement ($res->getBody());
                $result['status'] = (string) $response->Body->RESULT->SUCCESS;
                if ($response instanceof \SimpleXMLElement && (string) $response->Body->RESULT->SUCCESS == 'false') {
                    $xmlParserCreate = xml_parser_create();
                    xml_parse_into_struct($xmlParserCreate, $res->getBody(), $vals, $index);
                    xml_parser_free($xmlParserCreate);
                    $result['faultString'] = $vals[$index['FAULTSTRING'][0]]['value'];
                }
            }   catch (GuzzleException $e) {
                throw new Exception('Unable to schedule mailing: ' . $e->getMessage());
            }
            $logoutResult = $this->logout($loginResult['sessionId']);
            return $result;
        } else {
            return $loginResult;
        } 
    }

    //Create Relational Table
    public function createRelationalTable($eventName, $eventDetail, $jsessionID) {

        //$loginResult = $this->login();

        //if (isset($loginResult['sessionId'])) {
            $xml =  '<Envelope>
                      <Body>
                        <CreateTable>
                          <TABLE_NAME>'.$eventName.'</TABLE_NAME>
                          <COLUMNS>
                            <COLUMN>
                              <NAME>Email</NAME>
                              <TYPE>EMAIL</TYPE>
                              <IS_REQUIRED>true</IS_REQUIRED>
                              <KEY_COLUMN>true</KEY_COLUMN>
                            </COLUMN>
                            <COLUMN>
                              <NAME>eventStartTime</NAME>
                              <TYPE>TEXT</TYPE>
                            </COLUMN>
                            <COLUMN>
                              <NAME>eventEndTime</NAME>
                              <TYPE>TEXT</TYPE>
                            </COLUMN>
                            <COLUMN>
                              <NAME>eventLocationAdditionalMsg</NAME>
                              <TYPE>TEXT</TYPE>
                            </COLUMN>
                            <COLUMN>
                              <NAME>eventLocationAddress</NAME>
                              <TYPE>TEXT</TYPE>
                            </COLUMN>
                            <COLUMN>
                              <NAME>eventLocationCity</NAME>
                              <TYPE>TEXT</TYPE>
                            </COLUMN>
                            <COLUMN>
                              <NAME>eventLocationName</NAME>
                              <TYPE>TEXT</TYPE>
                            </COLUMN>
                            <COLUMN>
                              <NAME>eventLocationPhone</NAME>
                              <TYPE>TEXT</TYPE>
                            </COLUMN>
                            <COLUMN>
                              <NAME>eventLocationState</NAME>
                              <TYPE>SELECTION</TYPE>
                              <DEFAULT></DEFAULT>
                              <SELECTION_VALUES>
                                <VALUE>AL</VALUE>
                                <VALUE>AK</VALUE>
                                <VALUE>AZ</VALUE>
                                <VALUE>AR</VALUE>
                                <VALUE>CA</VALUE>
                                <VALUE>CO</VALUE>
                                <VALUE>CT</VALUE>
                                <VALUE>DE</VALUE>
                                <VALUE>FL</VALUE>
                                <VALUE>GA</VALUE>
                                <VALUE>HI</VALUE>
                                <VALUE>ID</VALUE>
                                <VALUE>IL</VALUE>
                                <VALUE>IN</VALUE>
                                <VALUE>IA</VALUE>
                                <VALUE>KS</VALUE>
                                <VALUE>KY</VALUE>
                                <VALUE>LA</VALUE>
                                <VALUE>ME</VALUE>
                                <VALUE>MD</VALUE>
                                <VALUE>MA</VALUE>
                                <VALUE>MI</VALUE>
                                <VALUE>MN</VALUE>
                                <VALUE>MS</VALUE>
                                <VALUE>MO</VALUE>
                                <VALUE>MT</VALUE>
                                <VALUE>NE</VALUE>
                                <VALUE>NV</VALUE>
                                <VALUE>NH</VALUE>
                                <VALUE>NJ</VALUE>
                                <VALUE>NM</VALUE>
                                <VALUE>NY</VALUE>
                                <VALUE>NC</VALUE>
                                <VALUE>ND</VALUE>
                                <VALUE>OH</VALUE>
                                <VALUE>OK</VALUE>
                                <VALUE>OR</VALUE>
                                <VALUE>PA</VALUE>
                                <VALUE>RI</VALUE>
                                <VALUE>SC</VALUE>
                                <VALUE>SD</VALUE>
                                <VALUE>TN</VALUE>
                                <VALUE>TX</VALUE>
                                <VALUE>UT</VALUE>
                                <VALUE>VA</VALUE>
                                <VALUE>WA</VALUE>
                                <VALUE>WV</VALUE>
                                <VALUE>WI</VALUE>
                                <VALUE>WY</VALUE>
                                <VALUE>DC</VALUE>
                              </SELECTION_VALUES>
                            </COLUMN>
                            <COLUMN>
                              <NAME>eventLocationZip</NAME>
                              <TYPE>TEXT</TYPE>
                            </COLUMN>
                          </COLUMNS>
                        </CreateTable>
                      </Body>
                    </Envelope>';
            $this->url = "http://api2.ibmmarketingcloud.com/XMLAPI;jsessionid=".$jsessionID;
            $client = new Client();
            try {
                $res = $client->post($this->url, [
                'headers' => [
                    'content-type'     => 'text/xml; charset=UTF8',
                ],
                'body' => $xml
                ]);
                $response = new SimpleXMLElement ($res->getBody());
                $result['status'] = (string) $response->Body->RESULT->SUCCESS;
                if ($result['status'] == 'TRUE') {
                    
                    $result['rTableId'] = (string) $response->Body->RESULT->TABLE_ID;
                    $listIdJion = [
                        env("SILVERPOP_TEST_LIST_ID_ADMIN"),
                        env("SILVERPOP_SQUINT_TEST_LIST_ID_ADMIN"),
                        env("SILVERPOP_SQUINT_TEST_LIST_ID_APPROVAL"),
                        env("SILVERPOP_TEST_LIST_ID_APPROVAL"),
                        env("SILVERPOP_REVHEALTH_TEST_LIST_ID_ADMIN"),
                        env("SILVERPOP_REVHEALTH_TEST_LIST_ID_APPROVAL"),
                        env("SILVERPOP_ALLIANCE_TEST_LIST_ID_ADMIN"),
                        env("SILVERPOP_ALLIANCE_TEST_LIST_ID_APPROVAL"),
                        env("SILVERPOP_CELLO_TEST_LIST_ID_ADMIN"),
                        env("SILVERPOP_CELLO_TEST_LIST_ID_APPROVAL"),
                        env("SILVERPOP_DB_LIST_ID")
                    ];
                    foreach ($listIdJion as $key => $value) {
                        $joinTable = $this->joinTable($result['rTableId'], $value ,$jsessionID);
                    }
                    //$joinTable = $this->joinTable($result['rTableId'], $jsessionID);
                    if ($joinTable['status'] == 'success') {
                        $queryName = 'Relational_'.$eventName;
                        $RelationalQuery = $this->createRelationalQuery($queryName, $result['rTableId'], $jsessionID);
                        if ($RelationalQuery['status'] == 'success') {
                            $result['queryName'] = $RelationalQuery['queryName'];
                            $result['queryId'] = $RelationalQuery['listId'];

                            //insert update relational table
                            $insertUpdateRelationalTable = $this->InsertUpdateRelationalTable($result['rTableId'], $eventDetail, $jsessionID);
                        } else {
                            $result['relationQueryError'] = $RelationalQuery;
                        }
                    } else {
                        $result['joinTableError'] = $joinTable;
                    }
                    //$previewMailing = $this->previewMailing($result['MailingID'], $loginResult['sessionId']);
                    //$result['HTMLBody'] = $previewMailing['HTMLBody'];
                }
                if ($response instanceof \SimpleXMLElement && (string) $response->Body->RESULT->SUCCESS == 'false') {
                    $xmlParserCreate = xml_parser_create();
                    xml_parse_into_struct($xmlParserCreate, $res->getBody(), $vals, $index);
                    xml_parser_free($xmlParserCreate);
                    $result['faultString'] = $vals[$index['FAULTSTRING'][0]]['value'];
                }
            }   catch (GuzzleException $e) {
                throw new Exception('Unable to send mailing: ' . $e->getMessage());
            }
            //$logoutResult = $this->logout($loginResult['sessionId']);
            return $result;
        // } else {
        //     return $loginResult;
        // } 
    }

    // Join Relational Table with master table
    public function joinTable($tableId, $listId, $jsessionID) {
        $xml = '<Envelope>
                  <Body>
                    <JoinTable>
                      <TABLE_ID>'.$tableId.'</TABLE_ID>
                      <LIST_ID>'.$listId.'</LIST_ID>
                      <TABLE_VISIBILITY>1</TABLE_VISIBILITY>
                      <MAP_FIELD>
                        <LIST_FIELD>Email</LIST_FIELD>
                        <TABLE_FIELD>Email</TABLE_FIELD>
                      </MAP_FIELD>
                    </JoinTable>
                  </Body>
                </Envelope>';
        $this->url = "http://api2.ibmmarketingcloud.com/XMLAPI;jsessionid=".$jsessionID;
        $client = new Client();
            try {
                $res = $client->post($this->url, [
                'headers' => [
                    'content-type'     => 'text/xml; charset=UTF8',
                ],
                'body' => $xml
                ]);
                $response = new SimpleXMLElement ($res->getBody());
                $result['status'] = (string) $response->Body->RESULT->SUCCESS;
                if ($result['status'] == 'TRUE') {
                    
                    $result['status'] = 'success';
                }
                if ($response instanceof \SimpleXMLElement && (string) $response->Body->RESULT->SUCCESS == 'false') {
                    $xmlParserCreate = xml_parser_create();
                    xml_parse_into_struct($xmlParserCreate, $res->getBody(), $vals, $index);
                    xml_parser_free($xmlParserCreate);
                    $result['status'] = $vals[$index['FAULTSTRING'][0]]['value'];
                }
            }   catch (GuzzleException $e) {
                throw new Exception('Join Failed: ' . $e->getMessage());
            }
        return $result;
    }

    // Join Relational Table with master table
    public function createRelationalQuery($queryName, $tableId, $jsessionID) {
        $xml = '<Envelope>
                  <Body>
                    <CreateQuery>
                      <QUERY_NAME>'.$queryName.'</QUERY_NAME>
                      <PARENT_LIST_ID>'.$tableId.'</PARENT_LIST_ID>
                      <VISIBILITY>1</VISIBILITY>
                      <ALLOW_FIELD_CHANGE>0</ALLOW_FIELD_CHANGE>
                      <CRITERIA>
                        <TYPE>editable</TYPE>
                        <EXPRESSION>
                          <TYPE>TE</TYPE>
                          <COLUMN_NAME>Email</COLUMN_NAME>
                          <OPERATORS><![CDATA[IS NOT empty]]></OPERATORS>
                        </EXPRESSION>
                      </CRITERIA>
                    </CreateQuery>
                  </Body>
                </Envelope>';
        $this->url = "http://api2.ibmmarketingcloud.com/XMLAPI;jsessionid=".$jsessionID;
        $client = new Client();
            try {
                $res = $client->post($this->url, [
                'headers' => [
                    'content-type'     => 'text/xml; charset=UTF8',
                ],
                'body' => $xml
                ]);
                $response = new SimpleXMLElement ($res->getBody());
                $result['status'] = (string) $response->Body->RESULT->SUCCESS;

                if ($result['status'] == 'TRUE') {
                    
                    $result['status'] = 'success';
                    $result['listId'] = (string) $response->Body->RESULT->ListId;
                    $result['queryName'] = $queryName;
                }
                if ($response instanceof \SimpleXMLElement && (string) $response->Body->RESULT->SUCCESS == 'false') {
                    $xmlParserCreate = xml_parser_create();
                    xml_parse_into_struct($xmlParserCreate, $res->getBody(), $vals, $index);
                    xml_parser_free($xmlParserCreate);
                    $result['status'] = $vals[$index['FAULTSTRING'][0]]['value'];
                }
            }  catch (GuzzleException $e) {
                throw new Exception('Relation Query Failed: ' . $e->getMessage());
            }
        return $result;
    }

    //Get List of Relational Table
    public function relationalTableList() {
        

        $loginResult = $this->login();

        if (isset($loginResult['sessionId'])) {

            $xml =  '<Envelope>
                    <Body>
                    <GetLists>
                    <VISIBILITY>1</VISIBILITY>
                    <LIST_TYPE>15</LIST_TYPE>
                    </GetLists>
                    </Body>
                    </Envelope>';
            $this->url = "http://api2.ibmmarketingcloud.com/XMLAPI;jsessionid=".$loginResult['sessionId'];
            $client = new Client();
            try {
                $res = $client->post($this->url, [
                'headers' => [
                    'content-type'     => 'text/xml; charset=UTF8',
                ],
                'body' => $xml
                ]);
                $response = new SimpleXMLElement ($res->getBody());
                $result['status'] = (string) $response->Body->RESULT->SUCCESS;
                if ($result['status'] == 'TRUE') {
                    $listArr = $response->Body->RESULT->LIST;
                    $listCount = count($response->Body->RESULT->LIST);
                    foreach ($listArr as $key => $value) {
                        $result['RT'][(string) $value->ID] = (string) $value->NAME;
                    }
                    $result['RT']['count'] = $listCount;
                }
                if ($response instanceof \SimpleXMLElement && (string) $response->Body->RESULT->SUCCESS == 'false') {
                    $xmlParserCreate = xml_parser_create();
                    xml_parse_into_struct($xmlParserCreate, $res->getBody(), $vals, $index);
                    xml_parser_free($xmlParserCreate);
                    $result['faultString'] = $vals[$index['FAULTSTRING'][0]]['value'];
                }
            }   catch (GuzzleException $e) {
                throw new Exception('Unable to send mailing: ' . $e->getMessage());
            }
            $logoutResult = $this->logout($loginResult['sessionId']);
            return $result;
        } else {
            return $loginResult;
        } 
    }

    //Get List of Relational Table
    public function relationalTableDelete($tableId, $sessionId) {
        

        //$loginResult = $this->login();
        $this->url = "http://api2.ibmmarketingcloud.com/XMLAPI;jsessionid=".$sessionId;

        $xml = '<Envelope>
                <Body>
                <DeleteTable>
                <TABLE_ID>'.$tableId.'</TABLE_ID> 
                <TABLE_VISIBILITY>1</TABLE_VISIBILITY>
                </DeleteTable>
                </Body>
                </Envelope>';

        $client = new Client();
        try {
            $res = $client->post($this->url, [
            'headers' => [
                'content-type'     => 'text/xml; charset=UTF8',
            ],
            'body' => $xml
            ]);
            $response = new SimpleXMLElement ($res->getBody());
            $result['status'] = (string) $response->Body->RESULT->SUCCESS;
            $result['jobId'] = (string) $response->Body->RESULT->JOB_ID;
            
            if ($response instanceof \SimpleXMLElement && (string) $response->Body->RESULT->SUCCESS == 'false') {
                $xmlParserCreate = xml_parser_create();
                xml_parse_into_struct($xmlParserCreate, $res->getBody(), $vals, $index);
                xml_parser_free($xmlParserCreate);
                $result['faultString'] = $vals[$index['FAULTSTRING'][0]]['value'];
            }
        }   catch (GuzzleException $e) {
            throw new Exception('Unable to send mailing: ' . $e->getMessage());
        }

        return $result;     
    }
}