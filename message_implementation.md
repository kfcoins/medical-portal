# Implementation Plan for Messaging System

This document outlines the full, comprehensive implementation plan for the messaging feature, specifically focusing on adding a "Talk to a Pharmacist Near You" interaction flow that connects patients directly to available pharmacists using the existing messaging backend APIs.

## User Review Required
> [!NOTE]
> The backend endpoints for messaging (`MessageController.php`) and the database schema (`messages` table) are already fully implemented! 
> The `patient-messages.html` and `pharmacy-messages.html` pages also already exist and have the core logic to send and receive messages. 
> Therefore, this implementation plan focuses purely on **bridging the gap**: giving users a way to discover pharmacists and initiate a chat.

> [!IMPORTANT]
> **Design Decision: The Entry Point**
> We plan to add a **Floating Chat Action Button (FAB)** fixed to the bottom-right corner of the screen across the landing page and patient store. Clicking this button will open a "Select a Pharmacist" modal. 
> *Do you prefer a floating button, or a static button embedded in the "Medicine Catalogue" section?*

## Open Questions
> [!WARNING]
> 1. If a user clicks "Talk to a Pharmacist" but they are not logged in, they will be redirected to the login page. After logging in, should they be automatically taken to the chat selection, or just to the landing page as we previously set up?
> 2. Should we filter the list of pharmacists strictly to the user's region, or show all approved pharmacists?

## Proposed Changes

### Frontend Modifications

#### [MODIFY] `index.html` (and optionally `patient-store.html`)
- **Action**: Add a "Talk to a Pharmacist" Floating Action Button (FAB).
- **Action**: Add a hidden "Pharmacist Directory" modal.
- **Action**: Add JavaScript to fetch available agents from `../backend/api/agents` and populate the modal when the FAB is clicked.
- **Action**: If the user is logged out, clicking the button redirects to `login.html?redirect=chat`. If logged in, it opens the modal.
- **Action**: Each pharmacist in the modal will have a "Chat Now" button that links to `patient-messages.html?contact_id={user_id}&contact_name={pharmacy_name}`.

#### [MODIFY] `login.html`
- **Action**: Update the login redirect logic to check for `redirect === 'chat'`. If true, after login, it redirects the user back to the landing page and automatically opens the Pharmacist Directory modal.

#### [MODIFY] `patient-messages.html`
- **Action**: Update the page load JavaScript to parse URL parameters (`contact_id` and `contact_name`).
- **Action**: If a `contact_id` is passed, the JS will check if a conversation with this contact already exists in the sidebar. 
  - If it exists, it simulates a click on that conversation.
  - If it does NOT exist (new conversation), it will artificially inject a new conversation item at the top of the sidebar and select it, allowing the user to type their first message.
- **Action**: The `sendMessage` function will automatically handle the rest, as the backend `MessageController::sendMessage` already creates the message and establishes the thread.

### Backend Modifications
*(No changes required. The `AgentController` already lists agents, and `MessageController` handles all messaging.)*

## Verification Plan
### Manual Verification
1. I will log out and click the "Talk to a Pharmacist" button to verify it redirects to the login page.
2. I will log in, verify it automatically opens the directory modal.
3. I will select a pharmacy from the directory modal, verify it redirects to `patient-messages.html` with the correct URL parameters.
4. I will verify that a new chat thread is initialized and that sending a message successfully saves it and updates the UI.
