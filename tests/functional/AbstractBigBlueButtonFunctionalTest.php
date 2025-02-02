<?php
/**
 * BigBlueButton open source conferencing system - https://www.bigbluebutton.org/.
 *
 * Copyright (c) 2016-2018 BigBlueButton Inc. and by respective authors (see below).
 *
 * This program is free software; you can redistribute it and/or modify it under the
 * terms of the GNU Lesser General Public License as published by the Free Software
 * Foundation; either version 3.0 of the License, or (at your option) any later
 * version.
 *
 * BigBlueButton is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License along
 * with BigBlueButton; if not, see <http://www.gnu.org/licenses/>.
 */
namespace BigBlueButton\Tests\Functional;

use BigBlueButton\BigBlueButton;
use BigBlueButton\Http\Transport\TransportInterface;
use BigBlueButton\Parameters\DeleteRecordingsParameters;
use BigBlueButton\Parameters\EndMeetingParameters;
use BigBlueButton\Parameters\GetMeetingInfoParameters;
use BigBlueButton\Parameters\GetRecordingsParameters;
use BigBlueButton\Parameters\IsMeetingRunningParameters;
use BigBlueButton\Parameters\JoinMeetingParameters;
use BigBlueButton\Parameters\PublishRecordingsParameters;
use BigBlueButton\TestCase;

/**
 * Class BigBlueButtonIntegrationTest
 * @package BigBlueButton
 */
abstract class AbstractBigBlueButtonFunctionalTest extends TestCase
{
    /**
     * @var BigBlueButton
     */
    private $bbb;

    /**
     * Setup test class
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->bbb = new BigBlueButton(null, null, static::createTransport());
    }

    /**
     * @return TransportInterface
     */
    abstract protected static function createTransport(): TransportInterface;

    /* Check Connection */

    /**
     * Test Check Connection call
     */
    public function testIsConnectionWorking()
    {
        // Check with correct baseurl and correct secret
        $result = $this->bbb->isConnectionWorking();
        $this->assertTrue($result);
        // Check error message
        $error = $this->bbb->getConnectionError();
        $this->assertNull($error);

        // Check with wrong baseurl and correct secret
        $wrong_url_bbb = new BigBlueButton($this->faker->url);
        $result        = $wrong_url_bbb->isConnectionWorking();
        $this->assertFalse($result);
        // Check error message
        $error = $wrong_url_bbb->getConnectionError();
        $this->assertSame(BigBlueButton::CONNECTION_ERROR_BASEURL, $error);

        // Check with correct baseurl and wrong secret
        $wrong_secret_bbb = new BigBlueButton(null, $this->faker->text);
        $result           = $wrong_secret_bbb->isConnectionWorking();
        $this->assertFalse($result);
        // Check error message
        $error = $wrong_secret_bbb->getConnectionError();
        $this->assertSame(BigBlueButton::CONNECTION_ERROR_SECRET, $error);
    }

    /* API Version */

    /**
     * Test API version call
     */
    public function testApiVersion()
    {
        $apiVersion = $this->bbb->getApiVersion();
        $this->assertEquals('SUCCESS', $apiVersion->getReturnCode());
        $this->assertEquals('2.0', $apiVersion->getVersion());
        $this->assertTrue($apiVersion->success());
    }

    /* Create Meeting */

    /**
     * Test create meeting
     */
    public function testCreateMeeting()
    {
        $params = $this->generateCreateParams();
        $result = $this->bbb->createMeeting($this->getCreateMock($params));
        $this->assertEquals('SUCCESS', $result->getReturnCode());
        $this->assertTrue($result->success());
    }

    /**
     * Test create meeting with a document URL
     */
    public function testCreateMeetingWithDocumentUrl()
    {
        $params = $this->getCreateMock($this->generateCreateParams());
        $params->addPresentation('https://picsum.photos/3840/2160/?random');

        $result = $this->bbb->createMeeting($params);

        $this->assertCount(1, $params->getPresentations());
        $this->assertEquals('SUCCESS', $result->getReturnCode());
        $this->assertTrue($result->success());
    }

    /**
     * Test create meeting with a document URL and filename
     */
    public function testCreateMeetingWithDocumentUrlAndFileName()
    {
        $params = $this->getCreateMock($this->generateCreateParams());
        $params->addPresentation('https://picsum.photos/3840/2160/?random', null, 'placeholder.png');

        $result = $this->bbb->createMeeting($params);

        $this->assertCount(1, $params->getPresentations());
        $this->assertEquals('SUCCESS', $result->getReturnCode());
        $this->assertTrue($result->success());
    }

    /**
     * Test create meeting with a document URL
     */
    public function testCreateMeetingWithDocumentEmbedded()
    {
        $params = $this->getCreateMock($this->generateCreateParams());
        $params->addPresentation('bbb_logo.png', file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'bbb_logo.png'));

        $result = $this->bbb->createMeeting($params);

        $this->assertCount(1, $params->getPresentations());
        $this->assertEquals('SUCCESS', $result->getReturnCode());
        $this->assertTrue($result->success());
    }

    /**
     * Test create meeting with a multiple documents
     */
    public function testCreateMeetingWithMultiDocument()
    {
        $params = $this->getCreateMock($this->generateCreateParams());
        $params->addPresentation('https://picsum.photos/3840/2160/?random', null, 'presentation.png');
        $params->addPresentation('logo.png', file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'bbb_logo.png'));

        $result = $this->bbb->createMeeting($params);

        $this->assertCount(2, $params->getPresentations());
        $this->assertEquals('SUCCESS', $result->getReturnCode());
        $this->assertTrue($result->success());
    }

    /* Join Meeting */

    /**
     */
    public function testJoinMeeting()
    {
        $params = $this->generateCreateParams();
        $result = $this->bbb->createMeeting($this->getCreateMock($params));
        $this->assertEquals('SUCCESS', $result->getReturnCode(), 'Create meeting');
        $creationTime = $result->getCreationTime();

        $joinMeetingParams = $this->generateJoinMeetingParams();
        $joinMeetingParams = new JoinMeetingParameters($result->getMeetingId(), 'Foobar', $result->getAttendeePassword());
        $joinMeetingParams->setRedirect(false);
        $joinMeetingParams->setCreateTime(sprintf('%.0f', $creationTime));

        $joinMeeting = $this->bbb->joinMeeting($joinMeetingParams);
        $this->assertEquals('SUCCESS', $joinMeeting->getReturnCode(), 'Join meeting');
        $this->assertTrue($joinMeeting->success());
        $this->assertNotEmpty($joinMeeting->getAuthToken());
        $this->assertNotEmpty($joinMeeting->getUserId());
        $this->assertNotEmpty($joinMeeting->getSessionToken());
        $this->assertNotEmpty($joinMeeting->getGuestStatus());
        $this->assertNotEmpty($joinMeeting->getUrl());
    }

    /* End Meeting */

    public function testEndMeeting()
    {
        $meeting = $this->createRealMeeting($this->bbb);

        $endMeeting = new EndMeetingParameters($meeting->getMeetingId(), $meeting->getModeratorPassword());
        $result     = $this->bbb->endMeeting($endMeeting);
        $this->assertEquals('SUCCESS', $result->getReturnCode());
        $this->assertTrue($result->success());
    }

    public function testEndNonExistingMeeting()
    {
        $params = $this->generateEndMeetingParams();
        $result = $this->bbb->endMeeting($this->getEndMeetingMock($params));
        $this->assertEquals('FAILED', $result->getReturnCode());
        $this->assertTrue($result->failed());
    }

    /* Is Meeting Running */

    public function testIsMeetingRunning()
    {
        $result = $this->bbb->isMeetingRunning(new IsMeetingRunningParameters($this->faker->uuid));
        $this->assertEquals('SUCCESS', $result->getReturnCode());
        $this->assertTrue($result->success());
        $this->assertEquals(false, $result->isRunning());
    }

    /* Get Meetings */

    public function testGetMeetings()
    {
        $result = $this->bbb->getMeetings();
        $this->assertNotEmpty($result->getMeetings());
    }

    /* Get meeting info */

    public function testGetMeetingInfo()
    {
        $meeting = $this->createRealMeeting($this->bbb);

        $result = $this->bbb->getMeetingInfo(new GetMeetingInfoParameters($meeting->getMeetingId(), $meeting->getModeratorPassword()));
        $this->assertEquals('SUCCESS', $result->getReturnCode());
        $this->assertTrue($result->success());
    }

    public function testGetRecordings()
    {
        $result = $this->bbb->getRecordings(new GetRecordingsParameters());
        $this->assertEquals('SUCCESS', $result->getReturnCode());
        $this->assertTrue($result->success());
    }

    public function testPublishRecordings()
    {
        $result = $this->bbb->publishRecordings(new PublishRecordingsParameters('non-existing-id-' . $this->faker->sha1, true));
        $this->assertEquals('FAILED', $result->getReturnCode());
        $this->assertTrue($result->failed());
    }

    public function testDeleteRecordings()
    {
        $result = $this->bbb->deleteRecordings(new DeleteRecordingsParameters('non-existing-id-' . $this->faker->sha1));
        $this->assertEquals('FAILED', $result->getReturnCode());
        $this->assertTrue($result->failed());
    }

    public function testUpdateRecordings()
    {
        $params = $this->generateUpdateRecordingsParams();
        $result = $this->bbb->updateRecordings($this->getUpdateRecordingsParamsMock($params));
        $this->assertEquals('FAILED', $result->getReturnCode());
        $this->assertTrue($result->failed());
    }
}
