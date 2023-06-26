<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Form\RegisterType;

class RegisterController extends AbstractController
{
    public function showForm(Request $request): Response
    {
        $registerForm = $this->createForm(RegisterType::class);

        return $this->render('register/index.html.twig', [
            'form' => $registerForm->createView(),
        ]);
    }
}