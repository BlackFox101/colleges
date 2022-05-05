<?php

namespace App\Controller;

use App\Entity\College;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CollegesController extends AbstractController
{
    #[Route('/colleges', name: 'colleges')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $collegeRepo = $entityManager->getRepository(College::class);

        $colleges = $collegeRepo->findAll();
        return $this->render('colleges.html.twig', [
                'colleges' => $colleges
            ]);
    }
}
