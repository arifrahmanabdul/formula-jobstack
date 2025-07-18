<?php

namespace App\Application\Controllers;

use Medoo\Medoo;
use Slim\Views\Twig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LocationController
{
    protected Twig $view;
    protected Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view = $view;
        $this->db = $db;
    }

    public function index(Request $request, Response $response): Response
    {
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            return $this->getData($request, $response);
        }

        return $this->view->render($response, 'locations/admin/index.twig');
    }

    public function getData(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $page = isset($params['page']) ? (int)$params['page'] : 1;
            $limit = 10; // Jumlah item per halaman
            $offset = ($page - 1) * $limit;
            $search = isset($params['search']) ? trim($params['search']) : '';

            $whereConditions = ['archived' => 0];
            if (!empty($search)) {
                $whereConditions['OR'] = [
                    'city[~]' => $search,
                    'province[~]' => $search,
                    'country[~]' => $search
                ];
            }

            // Get total count for pagination
            $totalCount = $this->db->count('tbl_locations', $whereConditions);

            // Get locations with pagination
            $locations = $this->db->select('tbl_locations', '*', array_merge($whereConditions, [
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => [$offset, $limit]
            ]));

            $totalPages = ceil($totalCount / $limit);

            $responseData = [
                'success' => true,
                'data' => $locations,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalCount,
                    'items_per_page' => $limit
                ]
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $errorResponse = [
                'success' => false,
                'message' => 'Gagal memuat data lokasi: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($errorResponse));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'locations/admin/create.twig');
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $this->db->insert('tbl_locations', [
            'city'     => $data['city'],
            'province' => $data['province'],
            'country'  => $data['country']
        ]);
        return $response->withHeader('Location', '/admin/locations')->withStatus(302);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $location = $this->db->get('tbl_locations', '*', ['id' => $args['id']]);
        return $this->view->render($response, 'locations/admin/edit.twig', ['location' => $location]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $this->db->update('tbl_locations', [
            'city'     => $data['city'],
            'province' => $data['province'],
            'country'  => $data['country']
        ], ['id' => $args['id']]);
        return $response->withHeader('Location', '/admin/locations')->withStatus(302);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        
        // Soft delete (archive)
        $this->db->update('tbl_locations', ['archived' => 1], ['id' => $id]);

        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $response->getBody()->write(json_encode(['success' => true, 'message' => 'Lokasi berhasil dihapus']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $response->withHeader('Location', '/admin/locations')->withStatus(302);
    }
}