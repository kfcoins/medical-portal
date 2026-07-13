# Pharmacy Sidebar Implementation & Verification Plan

This document breaks down the implementation details into 7 distinct phases, focusing on one sidebar page at a time. The goal is to ensure 100% functionality and completeness for every page in the pharmacy portal.

## Phase 1: Dashboard (`pharmacy-dashboard.html`)
**Objective:** Provide a real-time overview of the pharmacy's operations.
- **Frontend Changes:**
  - Update the sidebar links to correctly point to all 7 pages.
  - Dynamically load the pharmacy's name into the "Welcome back" widget.
  - Fetch and display accurate counts for "Products in Stock", "Pending Prescriptions", and "Recent Orders".
- **Backend Changes:**
  - Ensure the `AgentController.php` has a robust dashboard statistics endpoint that returns accurate data specifically for the logged-in agent.

## Phase 2: Inventory (`pharmacy-inventory.html`)
**Objective:** Full CRUD (Create, Read, Update, Delete) capability for medicines.
- **Frontend Changes:**
  - Update the sidebar links to maintain navigation consistency.
  - Verify that the table populates with the correct agent's inventory.
  - Ensure the "Add New Product" modal correctly submits data to the backend.
  - Verify that "Edit" and "Delete" actions work without errors.
  - Add visual indicators for "Out of Stock" or "Low Stock" items.
- **Backend Changes:**
  - Verify `MedicineController.php` strictly filters medicines by `agent_id` so pharmacies only see and edit their own stock.
  - Ensure image uploads (if applicable) are handled securely.

## Phase 3: Orders (`pharmacy-orders.html`)
**Objective:** Streamline the processing of incoming patient orders.
- **Frontend Changes:**
  - Update the sidebar links.
  - Verify the orders table populates with orders assigned to this pharmacy.
  - Ensure the status update buttons (e.g., changing from `Pending` to `Confirmed`, then to `Dispensed` or `Delivered`) work seamlessly and update the UI instantly.
- **Backend Changes:**
  - Verify `OrderController.php` correctly updates the `status` in the database.
  - Ensure that updating the status triggers the `Mailer.php` to send an email notification to the patient (which was fixed previously).

## Phase 4: Prescriptions (`pharmacy-prescriptions.html`)
**Objective:** Manage orders that require a pharmacist to review an uploaded prescription.
- **Frontend Changes:**
  - Create the `pharmacy-prescriptions.html` page matching the existing design system.
  - Build a table/grid to list prescription-based orders.
  - Implement a modal to preview the uploaded prescription image/document clearly.
  - Add buttons to "Approve & Quote Price" or "Decline" the prescription.
- **Backend Changes:**
  - Create an endpoint (or modify `OrderController.php`) to fetch orders where `prescription IS NOT NULL` and status is pending for this agent.
  - Create logic to handle the approval flow of a prescription order.

## Phase 5: Messages (`pharmacy-messages.html`)
**Objective:** Build a fully working, database-backed two-way messaging system between patients and pharmacies.
- **Database Changes:**
  - Create a new `messages` table in the database containing: `id`, `sender_id`, `receiver_id`, `order_id` (optional for context), `message_text`, `is_read`, and `created_at`.
- **Backend Changes:**
  - Create a `MessageController.php` to handle saving and fetching chat histories.
  - Add routes (`GET /api/messages/:userId`, `POST /api/messages`).
- **Frontend Changes:**
  - Create `pharmacy-messages.html`.
  - Build a two-pane UI: A left pane listing recent contacts/patients, and a right pane showing the chat history.
  - Implement real-time or polling-based fetching of new messages.
  - Add a form to send new messages.

## Phase 6: Analytics (`pharmacy-analytics.html`)
**Objective:** Provide visual insights into pharmacy performance.
- **Frontend Changes:**
  - Create `pharmacy-analytics.html`.
  - Integrate a charting library (like Chart.js) to display sales trends over time (e.g., Revenue last 7 days).
  - Add summary cards for "Total Revenue", "Total Completed Orders", and "Top Selling Medicines".
- **Backend Changes:**
  - Create an endpoint `GET /api/agents/analytics` that performs SQL aggregations (SUM, COUNT) grouped by date or medicine to feed the frontend charts.

## Phase 7: Settings (`pharmacy-settings.html`)
**Objective:** Allow agents to manage their profile and business details.
- **Frontend Changes:**
  - Create `pharmacy-settings.html`.
  - Build a form to update fields such as Pharmacy Name, Region, Address, Bio, and Contact Phone.
  - Add an option to change the account password.
- **Backend Changes:**
  - Create an endpoint `PATCH /api/agents/settings` in `AgentController.php` to update the `agents` and `users` tables.
  - Ensure inputs are validated and sanitized before updating the database.
