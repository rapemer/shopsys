<?php

namespace Shopsys\FrameworkBundle\Model\Mail;

use Doctrine\ORM\EntityManagerInterface;
use Shopsys\FrameworkBundle\Component\Domain\Domain;
use Shopsys\FrameworkBundle\Component\UploadedFile\UploadedFileFacade;
use Shopsys\FrameworkBundle\Model\Order\Status\OrderStatusMailTemplateService;
use Shopsys\FrameworkBundle\Model\Order\Status\OrderStatusRepository;

class MailTemplateFacade
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Mail\MailTemplateRepository
     */
    protected $mailTemplateRepository;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Order\Status\OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Order\Status\OrderStatusMailTemplateService
     */
    protected $orderStatusMailTemplateService;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Domain\Domain
     */
    protected $domain;

    /**
     * @var \Shopsys\FrameworkBundle\Component\UploadedFile\UploadedFileFacade
     */
    protected $uploadedFileFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Mail\MailTemplateFactoryInterface
     */
    protected $mailTemplateFactory;

    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Shopsys\FrameworkBundle\Model\Mail\MailTemplateRepository $mailTemplateRepository
     * @param \Shopsys\FrameworkBundle\Model\Order\Status\OrderStatusRepository $orderStatusRepository
     * @param \Shopsys\FrameworkBundle\Model\Order\Status\OrderStatusMailTemplateService $orderStatusMailTemplateService
     * @param \Shopsys\FrameworkBundle\Component\Domain\Domain $domain
     * @param \Shopsys\FrameworkBundle\Component\UploadedFile\UploadedFileFacade $uploadedFileFacade
     * @param \Shopsys\FrameworkBundle\Model\Mail\MailTemplateFactoryInterface $mailTemplateFactory
     */
    public function __construct(
        EntityManagerInterface $em,
        MailTemplateRepository $mailTemplateRepository,
        OrderStatusRepository $orderStatusRepository,
        OrderStatusMailTemplateService $orderStatusMailTemplateService,
        Domain $domain,
        UploadedFileFacade $uploadedFileFacade,
        MailTemplateFactoryInterface $mailTemplateFactory
    ) {
        $this->em = $em;
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->orderStatusMailTemplateService = $orderStatusMailTemplateService;
        $this->domain = $domain;
        $this->uploadedFileFacade = $uploadedFileFacade;
        $this->mailTemplateFactory = $mailTemplateFactory;
    }

    /**
     * @param string $templateName
     * @param int $domainId
     * @return \Shopsys\FrameworkBundle\Model\Mail\MailTemplate
     */
    public function get($templateName, $domainId)
    {
        return $this->mailTemplateRepository->getByNameAndDomainId($templateName, $domainId);
    }

    /**
     * @param int $domainId
     * @return \Shopsys\FrameworkBundle\Model\Mail\MailTemplate[]
     */
    public function getOrderStatusMailTemplatesIndexedByOrderStatusId($domainId)
    {
        $orderStatuses = $this->orderStatusRepository->getAll();
        $mailTemplates = $this->mailTemplateRepository->getAllByDomainId($domainId);

        return $this->orderStatusMailTemplateService->getFilteredOrderStatusMailTemplatesIndexedByOrderStatusId(
            $orderStatuses,
            $mailTemplates
        );
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Mail\MailTemplateData[] $mailTemplatesData
     * @param int $domainId
     */
    public function saveMailTemplatesData(array $mailTemplatesData, $domainId)
    {
        foreach ($mailTemplatesData as $mailTemplateData) {
            $mailTemplate = $this->mailTemplateRepository->getByNameAndDomainId($mailTemplateData->name, $domainId);
            $mailTemplate->edit($mailTemplateData);
            if ($mailTemplateData->deleteAttachment === true) {
                $this->uploadedFileFacade->deleteUploadedFileByEntity($mailTemplate);
            }
            $this->uploadedFileFacade->uploadFile($mailTemplate, $mailTemplateData->attachment);
        }

        $this->em->flush();
    }

    /**
     * @param int $domainId
     * @return \Shopsys\FrameworkBundle\Model\Mail\AllMailTemplatesData
     */
    public function getAllMailTemplatesDataByDomainId($domainId)
    {
        $orderStatuses = $this->orderStatusRepository->getAll();
        $mailTemplates = $this->mailTemplateRepository->getAllByDomainId($domainId);

        $allMailTemplatesData = new AllMailTemplatesData();
        $allMailTemplatesData->domainId = $domainId;

        $registrationMailTemplatesData = new MailTemplateData();
        $registrationMailTemplate = $this->mailTemplateRepository
            ->findByNameAndDomainId(MailTemplate::REGISTRATION_CONFIRM_NAME, $domainId);
        if ($registrationMailTemplate !== null) {
            $registrationMailTemplatesData->setFromEntity($registrationMailTemplate);
        }
        $registrationMailTemplatesData->name = MailTemplate::REGISTRATION_CONFIRM_NAME;
        $allMailTemplatesData->registrationTemplate = $registrationMailTemplatesData;

        $resetPasswordMailTemplateData = new MailTemplateData();
        $resetPasswordMailTemplate = $this->mailTemplateRepository
            ->findByNameAndDomainId(MailTemplate::RESET_PASSWORD_NAME, $domainId);
        if ($resetPasswordMailTemplate !== null) {
            $resetPasswordMailTemplateData->setFromEntity($resetPasswordMailTemplate);
        }
        $resetPasswordMailTemplateData->name = MailTemplate::RESET_PASSWORD_NAME;
        $allMailTemplatesData->resetPasswordTemplate = $resetPasswordMailTemplateData;

        $allMailTemplatesData->orderStatusTemplates =
            $this->orderStatusMailTemplateService->getOrderStatusMailTemplatesData($orderStatuses, $mailTemplates);

        $personalAccessTemplateData = new MailTemplateData();
        $personaAccessTemplate = $this->mailTemplateRepository
            ->findByNameAndDomainId(MailTemplate::PERSONAL_DATA_ACCESS_NAME, $domainId);
        if ($personaAccessTemplate !== null) {
            $personalAccessTemplateData->setFromEntity($personaAccessTemplate);
        }
        $allMailTemplatesData->personalDataAccessTemplate = $personalAccessTemplateData;

        $personalRequestTemplateData = new MailTemplateData();
        $personalRequestTemplate = $this->mailTemplateRepository
            ->findByNameAndDomainId(MailTemplate::PERSONAL_DATA_EXPORT_NAME, $domainId);
        if ($personalRequestTemplate !== null) {
            $personalRequestTemplateData->setFromEntity($personalRequestTemplate);
        }
        $allMailTemplatesData->personalDataExportTemplate = $personalRequestTemplateData;

        return $allMailTemplatesData;
    }

    /**
     * @param string $name
     */
    public function createMailTemplateForAllDomains($name)
    {
        foreach ($this->domain->getAll() as $domainConfig) {
            $mailTemplate = $this->mailTemplateFactory->create($name, $domainConfig->getId(), new MailTemplateData());
            $this->em->persist($mailTemplate);
        }

        $this->em->flush();
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Mail\MailTemplate $mailTemplate
     * @return string[]
     */
    public function getMailTemplateAttachmentsFilepaths(MailTemplate $mailTemplate)
    {
        $filepaths = [];
        if ($this->uploadedFileFacade->hasUploadedFile($mailTemplate)) {
            $uploadedFile = $this->uploadedFileFacade->getUploadedFileByEntity($mailTemplate);
            $filepaths[] = $this->uploadedFileFacade->getAbsoluteUploadedFileFilepath($uploadedFile);
        }

        return $filepaths;
    }

    /**
     * @return bool
     */
    public function existsTemplateWithEnabledSendingHavingEmptyBodyOrSubject()
    {
        return $this->mailTemplateRepository->existsTemplateWithEnabledSendingHavingEmptyBodyOrSubject();
    }
}
