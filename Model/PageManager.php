<?php
namespace Kitpages\CmsBundle\Model;

use Kitpages\CmsBundle\Entity\Page;
use Kitpages\CmsBundle\Entity\PageZone;
use Kitpages\CmsBundle\Entity\PagePublish;
use Kitpages\CmsBundle\Entity\Zone;
use Kitpages\CmsBundle\Entity\Block;
use Kitpages\CmsBundle\Entity\ZonePublish;
use Kitpages\CmsBundle\Entity\ZoneBlock;
use Kitpages\CmsBundle\Event\PageEvent;
use Kitpages\CmsBundle\KitpagesCmsEvents;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Bundle\DoctrineBundle\Registry;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class PageManager
{
    ////
    // dependency injection
    ////
    protected $dispatcher = null;
    protected $doctrine = null;
    protected $zoneManager = null;
    protected $logger = null;

    public function __construct(
        Registry $doctrine,
        EventDispatcher $dispatcher,
        ZoneManager $zoneManager,
        LoggerInterface $logger
    )
    {
        $this->dispatcher = $dispatcher;
        $this->doctrine = $doctrine;
        $this->zoneManager = $zoneManager;
        $this->logger = $logger;
    }

    /**
     * @return EventDispatcher $dispatcher
     */
    public function getDispatcher() {
        return $this->dispatcher;
    }

    /**
     * @return Registry $doctrine
     */
    public function getDoctrine() {
        return $this->doctrine;
    }

    /**
     * @return $zoneManager
     */
    public function getZoneManager() {
        return $this->zoneManager;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    ////
    // actions
    ////

    public function unpendingDelete(Page $page)
    {
        // throw on event
        $event = new PageEvent($page);
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::onPagePendingDelete, $event);

        // preventable action
        if (!$event->isDefaultPrevented()) {
            $em = $this->getDoctrine()->getEntityManager();
            $page->setIsPendingDelete(0);
            $em->flush();
        }
        // throw after event
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::afterPagePendingDelete, $event);
    }

    public function pendingDelete(Page $page)
    {
        // throw on event
        $event = new PageEvent($page);
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::onPagePendingDelete, $event);

        // preventable action
        if (!$event->isDefaultPrevented()) {
            $em = $this->getDoctrine()->getEntityManager();
            $page->setIsPendingDelete(1);
            $em->flush();
        }
        // throw after event
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::afterPagePendingDelete, $event);
    }

    public function delete(Page $page)
    {
        // throw on event
        $event = new PageEvent($page);
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::onPageDelete, $event);

        // preventable action
        if (!$event->isDefaultPrevented()) {
            $em = $this->getDoctrine()->getEntityManager();
            $zoneManager = $this->getZoneManager();
            foreach($em->getRepository('KitpagesCmsBundle:Zone')->findByPage($page) as $zone){
                $nbr = $em->getRepository('KitpagesCmsBundle:PageZone')->nbrPageZoneByZoneWithPageDiff($zone, $page);
                if ($nbr == 0) {
                    $zoneManager->delete($zone);
                }
            }
            $em->remove($page);
            $em->flush();
        }
        // throw after event
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::afterPageDelete, $event);
    }

    public function publishPage(Page $page, array $listLayout, array $listRenderer, array $dataInheritanceList)
    {
        $event = new PageEvent($page, $listLayout);
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::onPagePublish, $event);
        if (! $event->isDefaultPrevented()) {
            $em = $this->getDoctrine()->getEntityManager();
            if ($page->getIsPendingDelete()) {
                $pagePublish = $em->getRepository('KitpagesCmsBundle:PagePublish')->findByPage($page);
                if (!is_null($pagePublish)) {
                    $em->remove($pagePublish);
                    $em->flush();
                }
                $this->delete($page);
            } else {
                // publish zone
                $em = $this->getDoctrine()->getEntityManager();
                foreach($em->getRepository('KitpagesCmsBundle:Zone')->findByPage($page) as $zone){
                    $this->getZoneManager()->publish($zone, $listRenderer);
                }
                $em->flush();
                // remove old pagePublish
                $zonePublish = null;
                $pagePublish = $em->getRepository('KitpagesCmsBundle:PagePublish')->findByPage($page);
                if (!is_null($pagePublish)) {
                    $em->remove($pagePublish);
                    $em->flush();
                }

                $zoneList = array();
                // create page publish
                foreach($em->getRepository('KitpagesCmsBundle:Zone')->findByPage($page) as $zone){
                    $zoneList[] = $zone->getId();
                }

                $data = $em->getRepository('KitpagesCmsBundle:Page')->getDataWithInheritance($page, $dataInheritanceList);

                $pagePublishNew = new PagePublish();
                $pagePublishNew->initByPage($page, $data);
                $pagePublishNew->setZoneList(array("zoneList"=>$zoneList));
                $page->setIsPublished(true);
                $page->setPagePublish($pagePublishNew);
                $em->flush();
            }
        }
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::afterPagePublish, $event);
    }

    public function publish(Page $page, array $layoutList, array $listRenderer, array $dataInheritanceList, $childrenPublish)
    {

        $event = new PageEvent($page, $layoutList);
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::onMultiplePagePublish, $event);
        if ($childrenPublish) {
            $em = $this->getDoctrine()->getEntityManager();
            //$pageChildren = $em->getRepository('KitpagesCmsBundle:Page')->children($page, true);
            $pageChildren = $em->getRepository('KitpagesCmsBundle:Page')->children($page, false, 'level', 'DESC');
            foreach($pageChildren as $pageChild) {
                $this->publishPage($pageChild, $layoutList, $listRenderer, $dataInheritanceList);
                //$this->publish($pageChild, $layoutList, $listRenderer, $dataInheritanceList, $childrenPublish);
            }
        }
        $this->publishPage($page, $layoutList, $listRenderer, $dataInheritanceList);
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::afterMultiplePagePublish, $event);
    }

    public function afterModify($page, $oldPageData)
    {
        if ($oldPageData != $page->getData()) {
            $page->setRealUpdatedAt(new \DateTime());
            $em = $this->getDoctrine()->getEntityManager();
            $em->flush();
            $this->unpublish($page);
            $event = new PageEvent($page);
            $this->getDispatcher()->dispatch(KitpagesCmsEvents::afterPageModify, $event);
        }
    }

    public function unpublish($page){
        $event = new PageEvent($page);
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::onPageUnpublish, $event);
        $em = $this->getDoctrine()->getEntityManager();
        $page->setIsPublished(false);
        $em->flush();
        $this->getDispatcher()->dispatch(KitpagesCmsEvents::afterPageUnpublish, $event);
    }

    public function createZoneInPage(Page $page, $locationInPage)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $zone = new Zone();
        $zone->setSlug('');
        $zone->setIsPublished(false);
        $em->persist($zone);
        $em->flush();
        $pageZone = new PageZone();
        $pageZone->setPage($page);
        $pageZone->setZone($zone);
        $pageZone->setLocationInPage($locationInPage);
        $em->persist($pageZone);
        $em->flush();
    }

    ////
    // event listener
    ////
    public function afterZoneUnpublish(Event $event)
    {
        $zone = $event->getZone();
        $em = $this->getDoctrine()->getEntityManager();
        foreach($em->getRepository('KitpagesCmsBundle:Page')->findByZone($zone) as $page) {
            $this->unpublish($page);
        }
    }




}
