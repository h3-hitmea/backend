<?php
// Path: src\Controller\RegistrationController.php
namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    private $entityManager;
    private $slugger;
    private $serializer;
    private $validator;

    public function __construct(
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ) {
        $this->entityManager = $entityManager;
        $this->slugger = $slugger;
        $this->serializer = $serializer;
        $this->validator = $validator;
    }

    /**
     * @Route("/api/register", name="api_registration", methods={"POST"})
     */
    public function register(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $form = $this->createForm(UserType::class, $user);

        $form->submit($data);
        if ($form->isSubmitted() && $form->isValid()) {
            // Gérez le fichier de photo ici
            $uploadedFile = $form['photo']->getData();

            // Vérifiez si un fichier a été téléchargé
            if ($uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename.'.'.$uploadedFile->guessExtension();

                // Renommez le fichier avec le nom de la personne
                $newFilename = $user->getNom().'_'.$user->getPrenom().'.'.$uploadedFile->guessExtension();

                // Déplacez le fichier vers le répertoire d'upload
                try {
                    $uploadedFile->move(
                        $this->getParameter('upload_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Gérer l'erreur si la sauvegarde du fichier échoue
                    return $this->json(['error' => 'Failed to upload file.'], Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                // Mettez à jour le nom de fichier dans l'entité User
                $user->setPhoto($newFilename);
            }

            // Validez l'entité User
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            // Enregistrez l'utilisateur en base de données
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->json(['message' => 'User registered successfully.'], Response::HTTP_CREATED);
        }

        // Form is not valid
        $errors = $this->getFormErrors($form);

        return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
    }

    /**
     * @Route("/api/register/success", name="api_registration_success", methods={"GET"})
     */
    public function registrationSuccess(): Response
    {
        return $this->json(['message' => 'Registration successful.'], Response::HTTP_OK);
    }

    private function getFormErrors($form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        foreach ($form->all() as $childForm) {
            if ($childForm->isValid()) {
                continue;
            }
            foreach ($childForm->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
        }
        return $errors;
    }
}