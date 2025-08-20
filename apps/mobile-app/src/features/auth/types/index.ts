export interface User {
  id: string;
  name: string;
  email: string;
  role: 'admin' | 'manager' | 'operator';
  tenant_id: string;
  last_login_at?: string;
  created_at?: string;
  updated_at?: string;
  avatar_url?: string;
  must_change_password?: boolean;
}

export interface AuthResponse {
  user: User;
  token: string;
  expires_at: string;
  remember_me?: boolean;
  access_token?: string;
  token_type?: string;
}

export interface LoginCredentials {
  email: string;
  password: string;
  remember_me?: boolean;
}

export interface InvitationAcceptance {
  name: string;
  password: string;
  password_confirmation: string;
}

// OAuth types
export interface OAuthInitResponse {
  success: boolean;
  data: {
    authorization_url: string;
    state: string;
    provider: string;
  };
}

export interface OAuthCallbackData {
  code: string;
  state: string;
}

export interface SocialProvider {
  id: 'google' | 'facebook' | 'instagram';
  name: string;
  color: string;
  icon: string;
}

export interface AuthError {
  message: string;
  errors?: Record<string, string[]>;
}

export interface AuthMetadata {
  rememberMe: boolean;
  expiresAt: string;
  userId: string;
}

// Navigation types for auth flow
export interface AuthStackParamList {
  Login: undefined;
  AcceptInvitation: { token: string };
  ForgotPassword: undefined;
}
