import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import Login from "./pages/Login";
import Dashboard from "./pages/Dashboard";
import Instruments from "./pages/Instruments";
import StockDetail from "./pages/StockDetail";
import Screening from "./pages/Screening";
import Orders from "./pages/Orders";
import RiskMonitor from "./pages/RiskMonitor";
import Settings from "./pages/Settings";
import { AuthProvider } from "./lib/auth";
import ProtectedRoute from "./components/ProtectedRoute";
import Layout from "./components/Layout";
import "./index.css";

ReactDOM.createRoot(document.getElementById("root")!).render(
  <React.StrictMode>
    <BrowserRouter basename="/dashboard">
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route
            path="/"
            element={
              <ProtectedRoute>
                <Layout>
                  <Dashboard />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/instruments"
            element={
              <ProtectedRoute>
                <Layout>
                  <Instruments />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/instruments/:id"
            element={
              <ProtectedRoute>
                <Layout>
                  <StockDetail />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/screening"
            element={
              <ProtectedRoute>
                <Layout>
                  <Screening />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/orders"
            element={
              <ProtectedRoute>
                <Layout>
                  <Orders />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/risk"
            element={
              <ProtectedRoute>
                <Layout>
                  <RiskMonitor />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/settings"
            element={
              <ProtectedRoute>
                <Layout>
                  <Settings />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  </React.StrictMode>
);
