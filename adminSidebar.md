# Admin Portal Implementation Plan

This document breaks down the implementation of the Admin side into **5 phases**. Each phase corresponds to a specific page or functionality group within the administrator dashboard, ensuring systematic progress and a complete feature set.

## Phase 1: Dashboard (`admin-dashboard.html`)
**Goal:** Create a high-level overview of the platform's metrics.
- **Backend (`AdminController.php`):** Ensure `GET /api/admin/stats` provides counts for total patients, active pharmacies, pending approvals, and possibly recent activity (e.g., recent orders or newest registrations).
- **Frontend:** Build the dashboard UI displaying key metrics using cards/widgets and the standard sidebar layout shown in the design.

## Phase 2: Approvals (`admin-approvals.html`)
**Goal:** Interface for administrators to review and approve/reject pharmacy registrations.
- **Backend:** 
  - Ensure `GET /api/admin/pending-pharmacies` fetches pharmacies awaiting approval.
  - Ensure `POST /api/admin/approve-pharmacy/:id` and `POST /api/admin/reject-pharmacy/:id` are functional and trigger email notifications.
- **Frontend:** Implement a table/grid to list pending applications, along with modals to view their submitted documents (e.g., Pharmacy License, ID Front/Back) and action buttons for Approval/Rejection (with reason).

## Phase 3: Pharmacies (`admin-pharmacies.html`)
**Goal:** View and manage all registered pharmacies on the platform.
- **Backend:** Create `GET /api/admin/pharmacies` to fetch all non-pending (active/rejected) pharmacies. Optionally add endpoint for suspending/reactivating a pharmacy.
- **Frontend:** Implement a searchable/filterable table displaying pharmacy details (Name, Council Reg No, Location, Status). Include a modal for viewing more detailed info.

## Phase 4: Patients (`admin-patients.html`)
**Goal:** View and manage all registered patients on the platform.
- **Backend:** Create `GET /api/admin/patients` to fetch all users with role = `patient`. Include basic statistics (like registration date).
- **Frontend:** Implement a searchable/filterable table displaying patient details (Name, Email, Phone).

## Phase 5: Settings (`admin-settings.html`)
**Goal:** Allow administrators to update their profile and security settings.
- **Backend:** Add `PUT /api/admin/profile` to update admin details and change password (can potentially reuse logic from `PatientController` adapted for admin).
- **Frontend:** Implement forms for updating Personal Information and changing Password, similar to the patient/pharmacy profile pages.
