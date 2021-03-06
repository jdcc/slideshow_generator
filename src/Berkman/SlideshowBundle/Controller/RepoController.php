<?php

namespace Berkman\SlideshowBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Berkman\SlideshowBundle\Entity\Repo;
use Berkman\SlideshowBundle\Form\RepoType;

/**
 * Repo controller.
 *
 */
class RepoController extends Controller
{
    /**
     * Lists all Repo entities.
     *
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entities = $em->getRepository('BerkmanSlideshowBundle:Repo')->findAll();

        return $this->render('BerkmanSlideshowBundle:Repo:index.html.twig', array(
            'entities' => $entities
        ));
    }

    /**
     * Finds and displays a Repo entity.
     *
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('BerkmanSlideshowBundle:Repo')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Repo entity.');
        }

        $deleteForm = $this->createDeleteForm($id);

        return $this->render('BerkmanSlideshowBundle:Repo:show.html.twig', array(
            'entity'      => $entity,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to create a new Repo entity.
     *
     */
    public function newAction()
    {
        $entity = new Repo();
        $form   = $this->createForm(new RepoType(), $entity);

        return $this->render('BerkmanSlideshowBundle:Repo:new.html.twig', array(
            'entity' => $entity,
            'form'   => $form->createView()
        ));
    }

    /**
     * Creates a new Repo entity.
     *
     */
    public function createAction()
    {
        $entity  = new Repo();
        $request = $this->getRequest();
        $form    = $this->createForm(new RepoType(), $entity);

        if ('POST' === $request->getMethod()) {
            $form->bindRequest($request);

            if ($form->isValid()) {
                $em = $this->getDoctrine()->getEntityManager();
                $em->persist($entity);
                $em->flush();

                return $this->redirect($this->generateUrl('repo_show', array('id' => $entity->getId())));
                
            }
        }

        return $this->render('BerkmanSlideshowBundle:Repo:new.html.twig', array(
            'entity' => $entity,
            'form'   => $form->createView()
        ));
    }

    /**
     * Displays a form to edit an existing Repo entity.
     *
     */
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('BerkmanSlideshowBundle:Repo')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Repo entity.');
        }

        $editForm = $this->createForm(new RepoType(), $entity);
        $deleteForm = $this->createDeleteForm($id);

        return $this->render('BerkmanSlideshowBundle:Repo:edit.html.twig', array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Edits an existing Repo entity.
     *
     */
    public function updateAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('BerkmanSlideshowBundle:Repo')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Repo entity.');
        }

        $editForm   = $this->createForm(new RepoType(), $entity);
        $deleteForm = $this->createDeleteForm($id);

        $request = $this->getRequest();

        if ('POST' === $request->getMethod()) {
            $editForm->bindRequest($request);

            if ($editForm->isValid()) {
                $em = $this->getDoctrine()->getEntityManager();
                $em->persist($entity);
                $em->flush();

                return $this->redirect($this->generateUrl('repo_edit', array('id' => $id)));
            }
        }

        return $this->render('BerkmanSlideshowBundle:Repo:edit.html.twig', array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a Repo entity.
     *
     */
    public function deleteAction($id)
    {
        $form = $this->createDeleteForm($id);
        $request = $this->getRequest();

        if ('POST' === $request->getMethod()) {
            $form->bindRequest($request);

            if ($form->isValid()) {
                $em = $this->getDoctrine()->getEntityManager();
                $entity = $em->getRepository('BerkmanSlideshowBundle:Repo')->find($id);

                if (!$entity) {
                    throw $this->createNotFoundException('Unable to find Repo entity.');
                }

                $em->remove($entity);
                $em->flush();
            }
        }

        return $this->redirect($this->generateUrl('repo'));
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
            ->add('id', 'hidden')
            ->getForm()
        ;
    }
}
