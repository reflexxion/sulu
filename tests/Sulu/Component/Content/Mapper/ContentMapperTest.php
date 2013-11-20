<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Content\Mapper;

use Jackalope\RepositoryFactoryJackrabbit;
use Jackalope\Session;
use PHPCR\NodeInterface;
use PHPCR\PropertyInterface;
use PHPCR\SimpleCredentials;
use PHPCR\Util\NodeHelper;
use ReflectionMethod;
use Sulu\Component\Content\Property;
use Sulu\Component\Content\StructureInterface;
use Sulu\Component\Content\Types\ResourceLocator;
use Sulu\Component\Content\Types\Rlp\Mapper\PhpcrMapper;
use Sulu\Component\Content\Types\Rlp\Strategy\TreeStrategy;
use Sulu\Component\Content\Types\TextArea;
use Sulu\Component\Content\Types\TextLine;
use Sulu\Component\PHPCR\NodeTypes\Content\ContentNodeType;
use Sulu\Component\PHPCR\NodeTypes\Base\SuluNodeType;
use Sulu\Component\PHPCR\NodeTypes\Path\PathNodeType;
use Sulu\Component\PHPCR\NodeTypes\PathHistory\PathHistoryNodeType;
use Sulu\Component\PHPCR\SessionFactory\SessionFactoryService;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\SecurityContextInterface;

/**
 * tests content mapper with tree strategy and phpcr mapper
 */
class ContentMapperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SessionFactoryService
     */
    public $sessionService;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    public $container;

    /**
     * @var ContentMapper
     */
    protected $mapper;

    /**
     * @var Session
     */
    protected $session;

    public function setUp()
    {
        $this->prepareMapper();

        NodeHelper::purgeWorkspace($this->session);
        $this->session->save();

        $cmf = $this->session->getRootNode()->addNode('cmf');
        $cmf->addMixin('mix:referenceable');

        $routes = $cmf->addNode('routes');
        $routes->addMixin('mix:referenceable');

        $contents = $cmf->addNode('contents');
        $contents->addMixin('mix:referenceable');

        $this->session->save();
    }

    private function prepareMapper()
    {
        $this->container = $this->getContainerMock();

        $this->mapper = new ContentMapper('/cmf/contents', '/cmf/routes');
        $this->mapper->setContainer($this->container);

        $this->prepareSession();
        $this->prepareRepository();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getContainerMock()
    {
        $this->sessionService = new SessionFactoryService(new RepositoryFactoryJackrabbit(), array(
            'url' => 'http://localhost:8080/server',
            'username' => 'admin',
            'password' => 'admin',
            'workspace' => 'default'
        ));

        $containerMock = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
        $containerMock->expects($this->any())
            ->method('get')
            ->will(
                $this->returnCallback(array($this, 'containerCallback'))
            );

        return $containerMock;
    }

    public function getStructureMock($type = 1)
    {
        $structureMock = $this->getMockForAbstractClass(
            '\Sulu\Component\Content\Structure',
            array('overview', 'asdf', 'asdf', 2400)
        );

        $method = new ReflectionMethod(
            get_class($structureMock), 'add'
        );

        $method->setAccessible(true);
        $method->invokeArgs(
            $structureMock,
            array(
                new Property('title', 'text_line')
            )
        );

        $method->invokeArgs(
            $structureMock,
            array(
                new Property('url', 'resource_locator')
            )
        );

        if ($type == 1) {
            $method->invokeArgs(
                $structureMock,
                array(
                    new Property('tags', 'text_line', false, false, 2, 10)
                )
            );

            $method->invokeArgs(
                $structureMock,
                array(
                    new Property('article', 'text_area')
                )
            );
        } elseif ($type == 2) {
            $method->invokeArgs(
                $structureMock,
                array(
                    new Property('blog', 'text_area')
                )
            );
        }

        return $structureMock;
    }

    public function getStructureManager()
    {

        $structureManagerMock = $this->getMock('\Sulu\Component\Content\StructureManagerInterface');
        $structureManagerMock->expects($this->any())
            ->method('getStructure')
            ->will($this->returnCallback(array($this, 'getStructureCallback')));

        return $structureManagerMock;
    }

    public function getStructureCallback()
    {
        $args = func_get_args();
        $structureKey = $args[0];

        if ($structureKey == 'overview') {
            return $this->getStructureMock(1);
        } elseif ($structureKey == 'simple') {
            return $this->getStructureMock(2);
        }

        return null;
    }

    public function containerCallback()
    {
        $resourceLocator = new ResourceLocator(new TreeStrategy(new PhpcrMapper($this->sessionService, '/cmf/routes')), 'not in use');

        $result = array(
            'sulu.phpcr.session' => $this->sessionService,
            'sulu.content.structure_manager' => $this->getStructureManager(),
            'sulu.content.type.text_line' => new TextLine('not in use'),
            'sulu.content.type.text_area' => new TextArea('not in use'),
            'sulu.content.type.resource_locator' => $resourceLocator,
            'security.context' => $this->getSecurityContextMock()
        );
        $args = func_get_args();

        return $result[$args[0]];
    }

    private function getSecurityContextMock(){
        $userMock = $this->getMock('\Sulu\Component\Security\UserInterface');
        $userMock->expects($this->any())
            ->method('getId')
            ->will($this->returnValue(1));

        $tokenMock = $this->getMock('\Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $tokenMock->expects($this->any())
            ->method('getUser')
            ->will($this->returnValue($userMock));

        $securityMock = $this->getMock('\Symfony\Component\Security\Core\SecurityContextInterface');
        $securityMock->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($tokenMock));

        return $securityMock;
    }

    private function prepareSession()
    {
        $parameters = array('jackalope.jackrabbit_uri' => 'http://localhost:8080/server');
        $factory = new RepositoryFactoryJackrabbit();
        $repository = $factory->getRepository($parameters);
        $credentials = new SimpleCredentials('admin', 'admin');
        $this->session = $repository->login($credentials, 'default');
    }

    public function prepareRepository()
    {
        $this->session->getWorkspace()->getNamespaceRegistry()->registerNamespace('sulu', 'http://sulu.io/phpcr');
        $this->session->getWorkspace()->getNodeTypeManager()->registerNodeType(new SuluNodeType(), true);
        $this->session->getWorkspace()->getNodeTypeManager()->registerNodeType(new PathNodeType(), true);
        $this->session->getWorkspace()->getNodeTypeManager()->registerNodeType(new PathHistoryNodeType(), true);
        $this->session->getWorkspace()->getNodeTypeManager()->registerNodeType(new ContentNodeType(), true);
    }

    public function tearDown()
    {
        if (isset($this->session)) {
            NodeHelper::purgeWorkspace($this->session);
            $this->session->save();
        }
    }

    public function testSave()
    {
        $data = array(
            'title' => 'Testtitle',
            'tags' => array(
                'tag1',
                'tag2'
            ),
            'url' => '/news/test',
            'article' => 'Test'
        );

        $this->mapper->save($data, 'overview', 'default', 'de', 1);

        $root = $this->session->getRootNode();
        $route = $root->getNode('cmf/routes/news/test');

        $content = $route->getPropertyValue('sulu:content');

        $this->assertEquals('Testtitle', $content->getProperty('title')->getString());
        $this->assertEquals('Test', $content->getProperty('article')->getString());
        $this->assertEquals(array('tag1', 'tag2'), $content->getPropertyValue('tags'));
        $this->assertEquals('overview', $content->getPropertyValue('sulu:template'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:creator'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:changer'));
    }

    public function testLoad()
    {
        $data = array(
            'title' => 'Testtitle',
            'tags' => array(
                'tag1',
                'tag2'
            ),
            'url' => '/news/test',
            'article' => 'Test'
        );

        $structure = $this->mapper->save($data, 'overview', 'default', 'de', 1);

        $content = $this->mapper->load($structure->getUuid(), 'default', 'de');

        $this->assertEquals('Testtitle', $content->title);
        $this->assertEquals('Test', $content->article);
        $this->assertEquals('/news/test', $content->url);
        $this->assertEquals(array('tag1', 'tag2'), $content->tags);
        $this->assertEquals(1, $content->creator);
        $this->assertEquals(1, $content->changer);
    }

    public function testNewProperty()
    {
        $data = array(
            'title' => 'Testtitle',
            'tags' => array(
                'tag1',
                'tag2'
            ),
            'url' => '/news/test',
            'article' => 'Test'
        );

        $contentBefore = $this->mapper->save($data, 'overview', 'default', 'de', 1);

        $root = $this->session->getRootNode();
        $route = $root->getNode('cmf/routes/news/test');

        /** @var NodeInterface $contentNode */
        $contentNode = $route->getPropertyValue('sulu:content');

        // simulate new property article, by deleting the property
        /** @var PropertyInterface $articleProperty */
        $articleProperty = $contentNode->getProperty('article');
        $this->session->removeItem($articleProperty->getPath());
        $this->session->save();

        // simulates a new request
        $this->prepareMapper();

        /** @var StructureInterface $content */
        $content = $this->mapper->load($contentBefore->getUuid(), 'default', 'de');

        // test values
        $this->assertEquals('Testtitle', $content->title);
        $this->assertEquals(null, $content->article);
        $this->assertEquals('/news/test', $content->url);
        $this->assertEquals(array('tag1', 'tag2'), $content->tags);
        $this->assertEquals(1, $content->creator);
        $this->assertEquals(1, $content->changer);
    }

    public function testLoadByRL()
    {
        $data = array(
            'title' => 'Testtitle',
            'tags' => array(
                'tag1',
                'tag2'
            ),
            'url' => '/news/test',
            'article' => 'Test'
        );

        $this->mapper->save($data, 'overview', 'default', 'de', 1);

        $content = $this->mapper->loadByResourceLocator('/news/test', 'default', 'de');

        $this->assertEquals('Testtitle', $content->title);
        $this->assertEquals('Test', $content->article);
        $this->assertEquals('/news/test', $content->url);
        $this->assertEquals(array('tag1', 'tag2'), $content->tags);
        $this->assertEquals(1, $content->creator);
        $this->assertEquals(1, $content->changer);
    }

    public function testUpdate()
    {
        $data = array(
            'title' => 'Testtitle',
            'tags' => array(
                'tag1',
                'tag2'
            ),
            'url' => '/news/test',
            'article' => 'Test'
        );

        // save content
        $this->mapper->save($data, 'overview', 'default', 'de', 1);

        // change simple content
        $data['tags'][] = 'tag3';
        $data['tags'][0] = 'thats cool';
        $data['article'] = 'thats a new test';

        // update content
        $this->mapper->save($data, 'overview', 'default', 'de', 1);

        // check read
        $content = $this->mapper->loadByResourceLocator('/news/test', 'default', 'de');

        $this->assertEquals('Testtitle', $content->title);
        $this->assertEquals('thats a new test', $content->article);
        $this->assertEquals('/news/test', $content->url);
        $this->assertEquals(array('thats cool', 'tag2', 'tag3'), $content->tags);
        $this->assertEquals(1, $content->creator);
        $this->assertEquals(1, $content->changer);

        // check repository
        $root = $this->session->getRootNode();
        $route = $root->getNode('cmf/routes/news/test');

        $content = $route->getPropertyValue('sulu:content');

        $this->assertEquals('Testtitle', $content->getProperty('title')->getString());
        $this->assertEquals('thats a new test', $content->getProperty('article')->getString());
        $this->assertEquals(array('thats cool', 'tag2', 'tag3'), $content->getPropertyValue('tags'));
        $this->assertEquals('overview', $content->getPropertyValue('sulu:template'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:creator'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:changer'));
    }

    public function testPartialUpdate()
    {
        $data = array(
            'title' => 'Testtitle',
            'tags' => array(
                'tag1',
                'tag2'
            ),
            'url' => '/news/test',
            'article' => 'Test'
        );

        // save content
        $this->mapper->save($data, 'overview', 'default', 'de', 1);

        // change simple content
        $data['tags'][] = 'tag3';
        unset($data['tags'][0]);
        unset($data['article']);

        // update content
        $this->mapper->save($data, 'overview', 'default', 'de', 1);

        // check read
        $content = $this->mapper->loadByResourceLocator('/news/test', 'default', 'de');

        $this->assertEquals('Testtitle', $content->title);
        $this->assertEquals('Test', $content->article);
        $this->assertEquals('/news/test', $content->url);
        $this->assertEquals(array('tag2', 'tag3'), $content->tags);
        $this->assertEquals(1, $content->creator);
        $this->assertEquals(1, $content->changer);

        // check repository
        $root = $this->session->getRootNode();
        $route = $root->getNode('cmf/routes/news/test');

        $content = $route->getPropertyValue('sulu:content');

        $this->assertEquals('Testtitle', $content->getProperty('title')->getString());
        $this->assertEquals('Test', $content->getProperty('article')->getString());
        $this->assertEquals(array('tag2', 'tag3'), $content->getPropertyValue('tags'));
        $this->assertEquals('overview', $content->getPropertyValue('sulu:template'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:creator'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:changer'));
    }

    public function testNonPartialUpdate()
    {
        $data = array(
            'title' => 'Testtitle',
            'tags' => array(
                'tag1',
                'tag2'
            ),
            'url' => '/news/test',
            'article' => 'Test'
        );

        // save content
        $this->mapper->save($data, 'overview', 'default', 'de', 1);

        // change simple content
        $data['tags'][] = 'tag3';
        unset($data['tags'][0]);
        unset($data['article']);

        // update content
        $this->mapper->save($data, 'overview', 'default', 'de', 1, false);

        // check read
        $content = $this->mapper->loadByResourceLocator('/news/test', 'default', 'de');

        $this->assertEquals('Testtitle', $content->title);
        $this->assertEquals(null, $content->article);
        $this->assertEquals('/news/test', $content->url);
        $this->assertEquals(array('tag2', 'tag3'), $content->tags);
        $this->assertEquals(1, $content->creator);
        $this->assertEquals(1, $content->changer);

        // check repository
        $root = $this->session->getRootNode();
        $route = $root->getNode('cmf/routes/news/test');

        $content = $route->getPropertyValue('sulu:content');

        $this->assertEquals('Testtitle', $content->getProperty('title')->getString());
        $this->assertEquals(false, $content->hasProperty('article'));
        $this->assertEquals(array('tag2', 'tag3'), $content->getPropertyValue('tags'));
        $this->assertEquals('overview', $content->getPropertyValue('sulu:template'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:creator'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:changer'));
    }

    public function testUpdateNullValue()
    {
        $data = array(
            'title' => 'Testtitle',
            'tags' => array(
                'tag1',
                'tag2'
            ),
            'url' => '/news/test',
            'article' => 'Test'
        );

        // save content
        $this->mapper->save($data, 'overview', 'default', 'de', 1);

        // change simple content
        $data['tags'] = null;
        $data['article'] = null;

        // update content
        $this->mapper->save($data, 'overview', 'default', 'de', 1, false);

        // check read
        $content = $this->mapper->loadByResourceLocator('/news/test', 'default', 'de');

        $this->assertEquals('Testtitle', $content->title);
        $this->assertEquals(null, $content->article);
        $this->assertEquals('/news/test', $content->url);
        $this->assertEquals(null, $content->tags);
        $this->assertEquals(1, $content->creator);
        $this->assertEquals(1, $content->changer);

        // check repository
        $root = $this->session->getRootNode();
        $route = $root->getNode('cmf/routes/news/test');

        $content = $route->getPropertyValue('sulu:content');

        $this->assertEquals('Testtitle', $content->getProperty('title')->getString());
        $this->assertEquals(false, $content->hasProperty('article'));
        $this->assertEquals(false, $content->hasProperty('tags'));
        $this->assertEquals('overview', $content->getPropertyValue('sulu:template'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:creator'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:changer'));
    }

    public function testUpdateTemplate()
    {
        $data = array(
            'title' => 'Testtitle',
            'tags' => array(
                'tag1',
                'tag2'
            ),
            'url' => '/news/test',
            'article' => 'Test'
        );

        // save content
        $this->mapper->save($data, 'overview', 'default', 'de', 1);

        // change simple content
        $data = array(
            'title' => 'Testtitle',
            'blog' => 'this is a blog test'
        );

        // update content
        $this->mapper->save($data, 'simple', 'default', 'de', 1);

        // check read
        $content = $this->mapper->loadByResourceLocator('/news/test', 'default', 'de');

        // old properties not exists in structure
        $this->assertEquals(false, $content->hasProperty('article'));
        $this->assertEquals(false, $content->hasProperty('tags'));

        // old properties are right
        $this->assertEquals('Testtitle', $content->title);
        $this->assertEquals('/news/test', $content->url);
        $this->assertEquals(1, $content->creator);
        $this->assertEquals(1, $content->changer);

        // new property is set
        $this->assertEquals('this is a blog test', $content->blog);

        // check repository
        $root = $this->session->getRootNode();
        $route = $root->getNode('cmf/routes/news/test');
        $content = $route->getPropertyValue('sulu:content');

        // old properties exists in node
        $this->assertEquals('Test', $content->getPropertyValue('article'));
        $this->assertEquals(array('tag1', 'tag2'), $content->getPropertyValue('tags'));

        // property of new structure exists
        $this->assertEquals('Testtitle', $content->getProperty('title')->getString());
        $this->assertEquals('this is a blog test', $content->getPropertyValue('blog'));
        $this->assertEquals('simple', $content->getPropertyValue('sulu:template'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:creator'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:changer'));
    }

    public function testUpdateURL()
    {
        $data = array(
            'title' => 'Testtitle',
            'tags' => array(
                'tag1',
                'tag2'
            ),
            'url' => '/news/test',
            'article' => 'Test'
        );

        // save content
        $this->mapper->save($data, 'overview', 'default', 'de', 1);

        // change simple content
        $data['url'] = '/news/test/test/test';

        // update content
        $this->mapper->save($data, 'overview', 'default', 'de', 1);

        // check read
        $content = $this->mapper->loadByResourceLocator('/news/test/test/test', 'default', 'de');

        $this->assertEquals('Testtitle', $content->title);
        $this->assertEquals('Test', $content->article);
        $this->assertEquals('/news/test', $content->url);
        $this->assertEquals(array('tag1', 'tag2'), $content->tags);
        $this->assertEquals(1, $content->creator);
        $this->assertEquals(1, $content->changer);

        // check repository
        $root = $this->session->getRootNode();
        $route = $root->getNode('cmf/routes/news/test/test/test');

        $content = $route->getPropertyValue('sulu:content');

        $this->assertEquals('Testtitle', $content->getProperty('title')->getString());
        $this->assertEquals('Test', $content->getProperty('article')->getString());
        $this->assertEquals(array('tag1', 'tag2'), $content->getPropertyValue('tags'));
        $this->assertEquals('overview', $content->getPropertyValue('sulu:template'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:creator'));
        $this->assertEquals(1, $content->getPropertyValue('sulu:changer'));

        // FIXME comment in when history is running
//        // old resource locator is not a route (has property sulu:content), it is a history (has property sulu:route)
//        $oldRoute = $root->getNode('cmf/routes/news/test');
//        $this->assertFalse($oldRoute->hasProperty('sulu:content'));
//        $this->assertTrue($oldRoute->hasProperty('sulu:route'));
//
//        // history should reference to new route
//        $history = $oldRoute->getPropertyValue('sulu:route');
//        $this->assertEquals($route->getIdentifier(), $history->getIdentifier());
    }

}
