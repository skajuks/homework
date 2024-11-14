<?php

// assign magic strings and integers to named variables for easier maintenance and readability
// while reading docs i saw some httpCode class being used but could not find where it was defined so i made my own
class ResponseCodes
{
    public static $SUCCESS = 200;
    public static $CREATED = 201;
    public static $NO_CONTENT = 204;
    public static $UNAUTHORIZED = 401;
    public static $NOT_FOUND = 404;
    public static $BAD_REQUEST = 400;
}
class RequestMethods
{
    public static $POST = 'POST';
    public static $GET = 'GET';
    public static $PUT = 'PUT';
    public static $DELETE = 'DELETE';
}

// main test class
class APITests
{
    protected function _before() {}
    protected function _after() {}

    // Non-test private functions
    private function getUserId()
    {
        return '555'; // guaranteed that this user exists in db
    }
    private function getNonExistingUserId()
    {
        return '9999999999'; // guaranteed that this user does not exist in db
    }
    private function getAuthKey()
    {
        return 'myauthkey';
    }
    private function getTestUserData()
    {
        // using dry principle to avoid defining this in every test function while also making it available to change data as needs be
        return [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'dateOfBirth' => '1999-09-30',
            'personalIdDocument' => [
                'documentId' => 'AB123456',
                'countryOfIssue' => 'US',
                'validUntil' => '2030-12-31'
            ]
        ];
    }
    private function setHeaders($I, $auth)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        if ($auth) {
            $I->haveHttpHeader('basicAuth', $this->getAuthKey());
        }
    }
    private function sendRequest($I, $method, $url, $data = null, $auth = true)
    {
        $this->setHeaders($I, $auth);
        switch ($method) {
            case RequestMethods::$POST:
                $I->sendPOST($url, $data);
                break;
            case RequestMethods::$GET:
                $I->sendGET($url);
                break;
            case RequestMethods::$PUT:
                $I->sendPUT($url, $data);
                break;
            case RequestMethods::$DELETE:
                $I->sendDELETE($url);
                break;
        }
    }

    // ****************************************************************
    //                              TESTS
    // ****************************************************************

    // test for successful creation of a user with valid data
    public function testCreateUser(ApiTester $I)
    {
        $this->sendRequest($I, RequestMethods::$POST, '/users', $this->getTestUserData());
        $I->seeResponseCodeIs(ResponseCodes::$SUCCESS);
    }

    // test retrieving an existing user by id
    public function testGetUserById(ApiTester $I)
    {
        $this->sendRequest($I, RequestMethods::$GET, "/users/{$this->getUserId()}");
        $I->seeResponseCodeIs(ResponseCodes::$SUCCESS);
        $I->seeResponseContainsJson(['id' => $this->getUserId()]);
    }

    // see if invalid input is handled with proper error messages
    public function testInvalidUserCreation(ApiTester $I)
    {
        $testUser = $this->getTestUserData();
        $testUser['email'] = 'invalid-email'; // should not pass regex
        $testUser['personalIdDocument'] = []; // mandatory field empty

        $this->sendRequest($I, RequestMethods::$POST, '/users', $testUser);
        $I->seeResponseCodeIs(ResponseCodes::$BAD_REQUEST);
        $I->seeResponseContainsJson(['description' => 'Invalid Input']);
    }

    // update user details and verify the changes
    public function testUpdateUser(ApiTester $I)
    {
        $testUser = $this->getTestUserData();
        // update email and firstname
        $testUser['email'] = 'updated_email@example.com';
        $testUser['firstName'] = 'Jane';

        $this->sendRequest($I, RequestMethods::$PUT, "/users/{$this->getUserId()}", $testUser);
        $I->seeResponseCodeIs(ResponseCodes::$SUCCESS);

        // after update fetch this user to validate the changes
        $this->sendRequest($I, RequestMethods::$GET, "/users/{$this->getUserId()}");
        // TODO: finish this test

    }

    // deleting an existing user and validate that the user is no longer retrievable
    public function testDeleteUser(ApiTester $I)
    {
        $this->sendRequest($I, RequestMethods::$DELETE, "/users/{$this->getUserId()}");
        $I->seeResponseCodeIs(ResponseCodes::$NO_CONTENT);

        // try to retrieve the deleted user
        $this->sendRequest($I, RequestMethods::$GET, "/users/{$this->getUserId()}");
        $I->seeResponseCodeIs(ResponseCodes::$NOT_FOUND);
    }

    // ****************************************************************
    //                              EDGE CASES
    // ****************************************************************

    // some edge cases where we try to manipulate non existing entity
    public function testNonExistingUser(ApiTester $I)
    {
        $nonExistingUserId = $this->getNonExistingUserId();

        // Retrieve non-existing user
        $this->sendRequest($I, RequestMethods::$GET, "/users/{$nonExistingUserId}");
        $I->seeResponseCodeIs(ResponseCodes::$NOT_FOUND);

        // Update non-existing user
        $this->sendRequest($I, RequestMethods::$PUT, "/users/{$nonExistingUserId}", $this->getTestUserData());
        $I->seeResponseCodeIs(ResponseCodes::$NOT_FOUND);

        // Delete non-existing user
        $this->sendRequest($I, RequestMethods::$DELETE, "/users/{$nonExistingUserId}");
        $I->seeResponseCodeIs(ResponseCodes::$NOT_FOUND);
    }

    // test for cases with some wrong input parameters that should not be possible
    public function testInputEdgeCases(ApiTester $I)
    {
        // user should not be created due to non existing dateOfBirth
        // could also do another test where dateOfBirth is in the future
        // same can be done with validUntil with some date long in the future
        $testUser = $this->getTestUserData();
        $testUser['dateOfBirth'] = '1800-13-45';

        $this->sendRequest($I, RequestMethods::$POST, '/users', $testUser);
        $I->seeResponseCodeIs(ResponseCodes::$BAD_REQUEST);

        // More ideas:
        // - looks like there is no regex validation on [A-Z][a-z] (unless harcoded in backend not taken from db ) for both firstName and lastName ( migth be because Elon Musk has kids with some weird names )
        // we can try to create a user with some weird characters like emojis or some special characters such as this one - Ỏ̷͈̞̩͎̻̫̜͉̠̭̹̗͈̼̖͍͚̥̮̠̤̯̻̬̗̼̳̪̹͚̞̠̦̫̯̹͉̘͎̼̣̝̱̟̹̩̳̦̭͉̮̖̣̞̙̗̜̺̭̻̥͚̝̦̲̱͉̰͎̫̣̼͍̠̮͓̹͉̤̰̗̙͇̱̭̘̖̺̮̜̠͓̳̟̱̫̤̘̰̲͍͇̙͎̣̼̗̖̯͉̠̟͈͍̪͓̝̩̦̖̹̼̠̘̮͚̟͉̺̜͍͓̯̳̱̻̣͉̭͍̪͓̺̼̥̦̟͎̻̰_Ỏ̷͈̞̩͎̻̫̜͉̠̭̹̗͈̼̖͍͚̥̮̠̤̯̻̬

    }

    // ****************************************************************
    //                          PERFORMANCE TESTS
    // ****************************************************************

    // some basic performance test in case we want to get all the users, this probably wont work like this in real life
    // as it would probably use some sort of pagination mechanism instead of returning the whole thing
    public function testPerformance(ApiTester $I)
    {
        $startTime = microtime(true);

        $this->sendRequest($I, RequestMethods::$GET, '/users');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // lets say we have alot of users created and 2 seconds is the maximum time we want to wait for the response
        $I->assertLessThan(2, $executionTime, "API response time is too slow.");
    }

    // test creating multiple users in a loop and measure response time
    public function testCreateMultipleUsersPerformance(ApiTester $I)
    {
        $numUsers = 100; // number of users to create
        $maxTimePerUser = 1; // maximum time in seconds per user creation

        for ($i = 0; $i < $numUsers; $i++) {
            $testUser = $this->getTestUserData();
            $testUser['email'] = "user{$i}@example.com"; // lets say on user creation backend checks if such email already not in use so we can increment or use some rnd number generator

            $startTime = microtime(true);

            $this->sendRequest($I, RequestMethods::$POST, '/users', $testUser);

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            $I->seeResponseCodeIs(ResponseCodes::$SUCCESS);
            $I->assertLessThan($maxTimePerUser, $executionTime, "User creation took too long.");
        }
    }

    // ****************************************************************
    //                          SECURITY TESTS
    // ****************************************************************

    // test for xss vulnerability
    public function testXSSVulnerability(ApiTester $I)
    {
        $testUser = $this->getTestUserData();
        $testUser['firstName'] = '<script>alert("XSS")</script>';

        $this->sendRequest($I, RequestMethods::$POST, '/users', $testUser);
        $I->seeResponseCodeIs(ResponseCodes::$BAD_REQUEST);
        $I->seeResponseContainsJson(['description' => 'Invalid Input']); // i assume that this would be the resposne for such cases
    }

    // test accessing the API without authorization
    public function testAccessWithoutAuthorization(ApiTester $I)
    {
        $this->sendRequest($I, RequestMethods::$POST, '/users', $this->getTestUserData(), false);
        $I->seeResponseCodeIs(ResponseCodes::$UNAUTHORIZED);
        $I->seeResponseContainsJson(['title' => 'Unauthorized']);
    }

    // test for invalid authorization token
    public function testInvalidAuthorizationToken(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('basicAuth', 'bad-auth-key');
        $I->sendPOST('/users', $this->getTestUserData());

        $I->seeResponseCodeIs(ResponseCodes::$UNAUTHORIZED);
        $I->seeResponseContainsJson(['title' => 'Unauthorized']);
    }
}
