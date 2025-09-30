<?php

namespace BusBooking\Controllers;

use BusBooking\Core\Request;
use BusBooking\Core\Response;
use BusBooking\Services\BookingService;

/**
 * Booking controller for handling seat reservations
 */
class BookingController
{
    private BookingService $bookingService;

    public function __construct()
    {
        $this->bookingService = new BookingService();
    }

    public function createBooking(Request $request): array
    {
        $errors = $request->validate([
            'worker_id' => 'required',
            'schedule_id' => 'required|numeric',
            'date' => 'required'
        ]);

        if (!empty($errors)) {
            return Response::error('Validation failed', 400, $errors);
        }

        $workerId = $request->getBody('worker_id');
        $scheduleId = (int)$request->getBody('schedule_id');
        $date = $request->getBody('date');

        try {
            $result = $this->bookingService->bookSlot($workerId, $scheduleId, $date);
            return Response::success($result, $result['message']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    public function cancelBooking(Request $request): array
    {
        $errors = $request->validate([
            'worker_id' => 'required',
            'schedule_id' => 'required|numeric',
            'date' => 'required'
        ]);

        if (!empty($errors)) {
            return Response::error('Validation failed', 400, $errors);
        }

        $workerId = $request->getBody('worker_id');
        $scheduleId = (int)$request->getBody('schedule_id');
        $date = $request->getBody('date');

        try {
            $result = $this->bookingService->cancelBooking($workerId, $scheduleId, $date);
            return Response::success($result, $result['message']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    public function getBookingStatus(Request $request, array $params): array
    {
        $workerId = $params['worker_id'];
        $scheduleId = (int)$params['schedule_id'];
        $date = $params['date'];

        try {
            $status = $this->bookingService->getUserBookingStatus($workerId, $scheduleId, $date);
            return Response::success($status);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    public function getMyBookings(Request $request): array
    {
        if (!isset($request->user['user_id'])) {
            return Response::error('User not authenticated', 401);
        }

        $userId = $request->user['user_id'];
        $startDate = $request->getQuery('start_date');
        $endDate = $request->getQuery('end_date');

        try {
            $bookings = $this->bookingService->getMyBookings($userId, $startDate, $endDate);
            return Response::success(['bookings' => $bookings]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    public function searchBookings(Request $request, array $params): array
    {
        $workerId = $params['worker_id'];
        $date = $request->getQuery('date', date('Y-m-d'));

        try {
            // Get all schedules for the date
            $schedules = $this->bookingService->getAvailableSchedules($date);
            $bookingStatus = [];

            foreach ($schedules as $schedule) {
                $status = $this->bookingService->getUserBookingStatus($workerId, $schedule['id'], $date);
                $bookingStatus[] = [
                    'schedule' => $schedule,
                    'status' => $status
                ];
            }

            return Response::success(['bookings' => $bookingStatus]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    public function handleWebSocket(Request $request): array
    {
        // WebSocket implementation for real-time updates
        // This would require a proper WebSocket server implementation
        return Response::success(['message' => 'WebSocket endpoint - requires proper WebSocket server']);
    }
}