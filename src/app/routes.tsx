import React from 'react';
import { createBrowserRouter } from "react-router";
import { RootLayout } from "./components/RootLayout";
import { ProtectedRoute } from "./components/ProtectedRoute";
import { HomePage } from "./pages/HomePage";
import { AllItemsPage } from "./pages/AllItemsPage";
import { CategoryPage } from "./pages/CategoryPage";
import { ProductDetailPage } from "./pages/ProductDetailPage";
import { ShopPage } from "./pages/ShopPage";
import { CartPage } from "./pages/CartPage";
import { CheckoutPage } from "./pages/CheckoutPage";
import { ProfilePage } from "./pages/ProfilePage";
import { ContactPage } from "./pages/ContactPage";
import { FAQPage } from "./pages/FAQPage";
import { OrderTrackingPage } from "./pages/OrderTrackingPage";
import { LoginPage } from "./pages/LoginPage";
import { NotFoundPage } from "./pages/NotFoundPage";
import { ProductEntryPage } from "./pages/ProductEntryPage";
import { DriverPanelPage } from "./pages/DriverPanelPage";

export const router = createBrowserRouter([
  {
    path: "driver/:token",
    Component: DriverPanelPage,
  },
  {
    path: "/",
    Component: RootLayout,
    children: [
      { index: true, Component: HomePage },
      { path: "all-items", Component: AllItemsPage },
      { path: "category/:categoryId", Component: CategoryPage },
      { path: "shop/:shopId", Component: ShopPage },
      { path: "product/:productId", Component: ProductDetailPage },
      { path: "cart", Component: CartPage },
      { path: "checkout", element: <ProtectedRoute><CheckoutPage /></ProtectedRoute> },
      { path: "login", Component: LoginPage },
      { path: "profile", element: <ProtectedRoute><ProfilePage /></ProtectedRoute> },
      { path: "product-entry", element: <ProtectedRoute><ProductEntryPage /></ProtectedRoute> },
      { path: "product-entry/:productId", element: <ProtectedRoute><ProductEntryPage /></ProtectedRoute> },
      { path: "contact", Component: ContactPage },
      { path: "faq", Component: FAQPage },
      { path: "order-tracking", Component: OrderTrackingPage },
      { path: "order-tracking/:orderNumber", Component: OrderTrackingPage },
      { path: "*", Component: NotFoundPage },
    ],
  },
], { basename: import.meta.env.BASE_URL.replace(/\/$/, '') || '/' });