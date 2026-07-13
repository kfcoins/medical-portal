# PharmaTrust Ghana ðŸ¥

**Ghana's Trusted Pharmaceutical Agent Platform**  
*Final Year School Presentation Project â€” 2024*

---

## ðŸ“‹ Project Overview

PharmaTrust Ghana is a full-stack web platform that connects licensed pharmacy agents, pharmacists, patients, and healthcare providers across Ghana's 16 regions. The system tackles counterfeit medicines, medicine accessibility, and pharmaceutical supply chain management.

---

## ðŸ“ Project Structure

```
pharmacy-ghana/
â”œâ”€â”€ phase1-frontend/          # PHASE 1 â€” Pure HTML/CSS/JS Frontend
â”‚   â”œâ”€â”€ index.html            # Main landing page
â”‚   â”œâ”€â”€ login.html            # Login page
â”‚   â”œâ”€â”€ register.html         # Agent registration (multi-step)
â”‚   â”œâ”€â”€ css/style.css         # Full responsive stylesheet
â”‚   â””â”€â”€ js/main.js            # Interactive JavaScript
â”‚
â”œâ”€â”€ phase2-backend/           # PHASE 2 â€” Node.js/Express REST API
â”‚   â”œâ”€â”€ server.js             # Express server entry point
â”‚   â”œâ”€â”€ models/
â”‚   â”‚   â”œâ”€â”€ User.js           # User model (bcrypt passwords)
â”‚   â”‚   â””â”€â”€ Models.js         # Agent, Medicine, Order models
â”‚   â”œâ”€â”€ routes/
â”‚   â”‚   â”œâ”€â”€ auth.js           # Register, login, JWT auth
â”‚   â”‚   â”œâ”€â”€ agents.js         # Agent CRUD, dashboard stats
â”‚   â”‚   â”œâ”€â”€ medicines.js      # Medicine catalogue, QR verify
â”‚   â”‚   â”œâ”€â”€ orders.js         # Order management
â”‚   â”‚   â””â”€â”€ contact.js        # Contact form submissions
â”‚   â”œâ”€â”€ seed.js               # Database seeder with sample data
â”‚   â”œâ”€â”€ .env.example          # Environment variables template
â”‚   â””â”€â”€ package.json
â”‚
â””â”€â”€ phase3-fullstack/         # PHASE 3 â€” Integrated Full Stack App
    â”œâ”€â”€ frontend/ â†’ (copy of phase1 with API integration)
    â””â”€â”€ backend/  â†’ (copy of phase2 serving the frontend)
```

---

## ðŸš€ Getting Started

### Phase 1 â€” Frontend Only
Simply open `index.html` in any browser. No server needed.

### Phase 2 â€” Backend API

**Prerequisites:** Node.js 18+, MongoDB

```bash
cd phase2-backend
npm install
cp .env.example .env         # Edit your MongoDB URI
npm run seed                 # Seed sample data
npm run dev                  # Start dev server
```

API runs at: `http://localhost:3000`

### Phase 3 â€” Full Stack
```bash
cd phase3-fullstack/backend
npm install
npm start
```
Visit `http://localhost:3000` â€” serves both frontend + API.

---

## ðŸ”‘ API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/auth/register | Register new agent/user |
| POST | /api/auth/login | Login and get JWT token |
| GET | /api/auth/me | Get current user (protected) |
| GET | /api/agents | List all verified agents |
| GET | /api/agents/:id | Get agent by ID |
| GET | /api/medicines | Browse medicine catalogue |
| POST | /api/medicines/verify | Verify medicine by QR/batch |
| POST | /api/orders | Place an order (protected) |
| GET | /api/orders/my | Get my orders (protected) |
| POST | /api/contact | Submit contact message |

---

## ðŸŽ¨ Design System

| Token | Value | Usage |
|-------|-------|-------|
| `--green-500` | `#2D9A6A` | Primary brand color |
| `--green-900` | `#0D3B2E` | Dark sections (navbar) |
| `--gold-500` | `#E8A020` | Accents, badges |
| `--cream` | `#F8F4EC` | Hero background |
| Font Display | Sora | Headings |
| Font Body | Inter | Body text |

---

## ðŸŒ Key Features

- âœ… **Anti-counterfeit QR verification** using FDA Ghana database
- âœ… **NHIS integration** for instant health insurance processing
- âœ… **USSD support** (*920#) for feature phone users in rural Ghana
- âœ… **Multi-step agent registration** with document upload
- âœ… **Responsive design** â€” mobile-first, works on all devices
- âœ… **JWT authentication** with role-based access (patient/agent/admin)
- âœ… **16-region coverage** across Ghana
- âœ… **e-Prescription system** linking doctors to agents

---

## ðŸ‘¥ Test Credentials (after seeding)

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@PharmaTrust.com.gh | Admin@1234 |
| Agent | abena@PharmaTrust.com.gh | Agent@1234 |
| Patient | akosua@gmail.com | Patient@1234 |

---

## ðŸ“š Tech Stack

**Frontend:** HTML5, CSS3 (Custom Properties), Vanilla JS  
**Backend:** Node.js, Express.js  
**Database:** MongoDB + Mongoose ODM  
**Auth:** JWT (JSON Web Tokens) + bcryptjs  
**Fonts:** Google Fonts (Sora + Inter)  
**Icons:** Font Awesome 6  

---

*Â© 2024 PharmaTrust Ghanaana | Final Year Project | University of Ghana, School of Pharmacy*
