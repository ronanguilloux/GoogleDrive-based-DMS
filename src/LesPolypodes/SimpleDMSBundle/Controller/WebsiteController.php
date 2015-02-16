<?php
/**
 * This file is part of the SimpleDMS package.
 *
 * (c) 2015 Les Polypodes
 * Made in Nantes, France - http://lespolypodes.com
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 *
 * File created by ronan@lespolypodes.com
 */
namespace LesPolypodes\SimpleDMSBundle\Controller;

use LesPolypodes\SimpleDMSBundle\Service\GoogleDriveListParameters;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Class FileController
 * @package LesPolypodes\SimpleDMSBundle\Controller
 */
class WebsiteController extends Controller
{

    /**
     * @Route("/folders", name="_folders")
     * @Template()
     * @param Request $request
     *
     * @return array
     */
    public function foldersAction(Request $request, $id = null)
    {
    }

    /**
     * @Route("/folders/{folderId}", name="_folder")
     * @Template()
     * @param Request $request
     * @param string  $folderId
     *
     * @return array|RedirectResponse
     */
    public function folderAction(Request $request, $folderId)
    {
        if ($folderId === $this->get('google_drive')->getRootFolderId()) {
            return $this->redirect($this->generateUrl('_files'), 301);
        }
        $optParams = new GoogleDriveListParameters();
        if ($request->query->has("pageToken")) {
            $optParams->setPageToken($request->get("pageToken"));
        }

        $result = $this->get('google_drive')->getFilesList(false, $optParams, $folderId);

        $result['pagination'] = $this->buildPagination(
            $result['nextPageToken'],
            $optParams,
            $this->generateUrl('_folder', array('folderId' => $folderId))
        );
        $result['children'] = $this->get('google_drive')->getChildren($folderId);
        $result['folders'] = $this->get('google_drive')->getFolders($folderId);
        $result['total'] = count($result['children']);

        if ($request->query->has("pageToken")
            && !empty($result['nextPageToken'])
            && $request->query->get("pageToken") == $result['nextPageToken']) {
            $result['has_pagination'] = false;
        }

        return $result;
    }

    /**
     * @Route("/files", name="_files")
     * @Template()
     * @param Request $request
     *
     * @return array
     */
    public function filesAction(Request $request)
    {
        $form = $this->get('form.factory')->createNamedBuilder(
            '',
            'form',
            array('q' => null, 'pageToken' => null),
            array(
                'csrf_protection' => false,
            )
        )
            ->add('q', 'text', array('label' => ' ', 'required' => false))
            ->add('pageToken', 'hidden', array('label' => ' ', 'required' => false));
        $form->setMethod('GET');
        $form = $form->getForm();

        $data = array("q" => "");
        $form->handleRequest($request);
        if ($form->isValid()) {
            $data = $form->getData();
        }

        $pageToken = $request->get("pageToken"); // not a form field
        $optParams = new GoogleDriveListParameters($data['q'], $pageToken);
        $result = $this->get('google_drive')->getFilesList(false, $optParams);
        $result['folders'] = $this->get('google_drive')->getFolders();
        $result['form'] = $form->createView();
        $result['pagination'] = $this->buildPagination($result['nextPageToken'], $optParams);

        return $result;
    }

    /**
     * @param string                    $nextPageToken
     * @param GoogleDriveListParameters $optParams
     * @param string                    $prefixUrl     route Url
     *
     * @return array
     */
    protected function buildPagination($nextPageToken, $optParams = null, $prefixUrl = null)
    {
        $nextUrl = (!empty($prefixUrl)) ? $prefixUrl : null;
        $searchTerm = (!is_null($optParams)) ? $optParams->getSearchTerm() : '';
        if (!empty($nextPageToken)) {
            $nextUrl .= sprintf(
                "?q=%s&pageToken=%s",
                $searchTerm,
                $nextPageToken
            );
        }

        return array(
            'nextUrl' => $nextUrl,
        );
    }

    /**
     * @Route("/files/{fileId}", name="_file")
     * @Template()
     *
     * It forces a download response
     *
     * @param $fileId
     *
     * @return Response
     */
    public function fileAction($fileId)
    {
        $resource = $this->get('google_drive')->getFileMetadataAndContent($fileId);
        $file = $resource['file']['file'];
        $content = $resource['content'];
        $response = new Response($content, 200);
        $response->headers->set('Cache-Control', '');
        $response->headers->set('Content-Length', $file->fileSize);
        $response->headers->set('Content-Type', $file->mimeType);
        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s'));
        $contentDisposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->originalFilename);
        $response->headers->set('Content-Disposition', $contentDisposition);
        $response->send();

        return $response;
    }
}
