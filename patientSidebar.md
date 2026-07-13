# Patient Sidebar Implementation Plan

This document outlines the step-by-step implementation plan for the patient-side (user) of the PharmaTrust Ghana platform. 

The implementation is broken down into **6 Phases**, matching the items on the patient sidebar:

## Phase 1: Home (`patient-dashboard.html`)
**Goal:** Create a central overview for the patient upon logging in.
- **Frontend UI:** 
  - Summary cards (Active Prescriptions, Total Orders).
  - Quick links (Upload Prescription, Browse Store).
  - Recent activity or recent orders list.
- **Backend Requirements:**
  - Create `PatientController.php` (if needed) or add a dashboard stats endpoint (e.g., `GET /api/patient/dashboard`) to fetch real-time data for the logged-in user.

## Phase 2: My Prescriptions (`patient-prescriptions.html`)
**Goal:** Allow patients to manage and track their uploaded prescriptions.
- **Frontend UI:**
  - "Upload New Prescription" button/modal (File upload + notes).
  - List/Grid view of past prescriptions with their status (Pending, Quoted, Approved, Rejected).
  - View details of a specific prescription quote and action buttons to accept/decline.
- **Backend Requirements:**
  - Endpoint to fetch user's prescriptions (`GET /api/patient/prescriptions`).
  - Endpoint to upload a new prescription image securely (`POST /api/patient/prescriptions/upload`).

## Phase 3: Pharmacy Store (`patient-store.html`)
**Goal:** Enable patients to browse available medicines and make purchases.
- **Frontend UI:**
  - Search bar and category filters.
  - Grid view of medicines available from various pharmacies.
  - "Add to Cart" functionality and a persistent Cart interface (sidebar or modal).
  - Checkout flow (selecting delivery address and payment method).
- **Backend Requirements:**
  - Endpoint to list public medicines (`GET /api/medicines/public`).
  - Logic to handle the creation of an order upon checkout (`POST /api/orders/checkout`).

## Phase 4: Order History (`patient-orders.html`)
**Goal:** Let patients view their active and past orders.
- **Frontend UI:**
  - Table or card list of all orders.
  - Status badges (Pending, Confirmed, Dispensed, Delivered).
  - Modal to view order items, pricing breakdown, and pharmacy details.
- **Backend Requirements:**
  - Endpoint to fetch orders specifically for the logged-in patient (`GET /api/patient/orders`).
  - Make sure the existing `OrderController.php` can handle patient-filtered requests.

## Phase 5: Messages (`patient-messages.html`)
**Goal:** Provide a communication channel between patients and pharmacies.
- **Frontend UI:**
  - Two-pane layout similar to the pharmacy side (Conversations on the left, Active chat on the right).
- **Backend Requirements:**
  - Re-use the existing `MessageController.php` endpoints. It automatically detects the logged-in user's role and fetches the correct history/conversations. 

## Phase 6: Profile (`patient-profile.html`)
**Goal:** Allow patients to manage their account settings and personal information.
- **Frontend UI:**
  - Form to update Personal Details (First Name, Last Name, Phone, Address/Region).
  - Form to update Security Settings (Change Password).
- **Backend Requirements:**
  - Re-use `GET /api/auth/me` to load profile data.
  - Create or use a `PUT /api/patient/profile` endpoint to update patient-specific fields.
  - Re-use `PUT /api/auth/change-password` for security updates.

---
**Next Steps:** Review this breakdown, and once approved, we will begin execution starting with **Phase 1: Home**.
