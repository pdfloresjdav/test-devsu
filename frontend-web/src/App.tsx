import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { AuthProvider } from './auth/AuthProvider';
import { ProtectedRoute } from './components/ProtectedRoute';
import { LoginPage } from './pages/LoginPage';
import { CallbackPage } from './pages/CallbackPage';
import { MovimientosPage } from './pages/MovimientosPage';
import { TransferenciaPage } from './pages/TransferenciaPage';
import { ConfirmacionPage } from './pages/ConfirmacionPage';

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/callback" element={<CallbackPage />} />
          <Route
            path="/"
            element={
              <ProtectedRoute>
                <MovimientosPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/transferencias"
            element={
              <ProtectedRoute>
                <TransferenciaPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/transferencias/confirmacion"
            element={
              <ProtectedRoute>
                <ConfirmacionPage />
              </ProtectedRoute>
            }
          />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;
