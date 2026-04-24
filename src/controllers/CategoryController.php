<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/CategoryRepository.php';

class CategoryController extends AppController
{
    private CategoryRepository $categories;

    public function __construct()
    {
        $this->categories = new CategoryRepository();
    }

    public function list(array $params): void
    {
        $this->requireLogin();
        $this->jsonResponse(['categories' => $this->categories->findAll()]);
    }
}
