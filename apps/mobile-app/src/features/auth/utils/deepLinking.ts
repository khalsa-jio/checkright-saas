import * as Linking from 'expo-linking';
import { router } from 'expo-router';

/**
 * Deep linking utility for handling invitation URLs
 * Updated for expo-router instead of React Navigation
 */
export class DeepLinkingService {
  private static readonly INVITATION_URL_PATTERN =
    /\/invitations\/([a-f0-9]{64})\/accept/i;

  /**
   * Parse invitation token from URL
   * @param url The invitation URL
   * @returns The invitation token or null if invalid
   */
  static parseInvitationToken(url: string): string | null {
    try {
      const match = url.match(this.INVITATION_URL_PATTERN);
      return match ? match[1] : null;
    } catch (error) {
      console.warn('Failed to parse invitation URL:', error);
      return null;
    }
  }

  /**
   * Validate invitation URL format
   * @param url The URL to validate
   * @returns True if the URL is a valid invitation URL
   */
  static isValidInvitationUrl(url: string): boolean {
    return this.INVITATION_URL_PATTERN.test(url);
  }

  /**
   * Handle incoming URLs and route appropriately using expo-router
   * @param url The incoming URL
   * @returns True if URL was handled, false otherwise
   */
  static handleIncomingUrl(url: string): boolean {
    try {
      const token = this.parseInvitationToken(url);

      if (token) {
        // Navigate to invitation acceptance screen using expo-router
        router.push(`/(auth)/accept-invitation?token=${token}`);
        return true;
      }

      return false;
    } catch (error) {
      console.warn('Failed to handle incoming URL:', error);
      return false;
    }
  }

  /**
   * Set up deep link event listeners using expo-linking
   * @returns Cleanup function to remove listeners
   */
  static setupDeepLinkListeners(): () => void {
    let isInitialUrlHandled = false;

    // Handle app opened with URL when app is already running
    const urlSubscription = Linking.addEventListener('url', (event) => {
      this.handleIncomingUrl(event.url);
    });

    // Handle app opened with URL when app is closed/backgrounded
    Linking.getInitialURL().then((url) => {
      if (url && !isInitialUrlHandled) {
        isInitialUrlHandled = true;
        this.handleIncomingUrl(url);
      }
    });

    // Return cleanup function
    return () => {
      urlSubscription?.remove();
    };
  }

  /**
   * Generate invitation URL for testing purposes
   * @param token The invitation token
   * @param baseUrl The base URL of the application
   * @returns The complete invitation URL
   */
  static generateInvitationUrl(
    token: string,
    baseUrl: string = 'https://checkright.app'
  ): string {
    return `${baseUrl}/invitations/${token}/accept`;
  }

  /**
   * Open invitation URL in the default browser
   * @param token The invitation token
   * @param baseUrl The base URL of the application
   */
  static async openInvitationUrl(
    token: string,
    baseUrl?: string
  ): Promise<void> {
    try {
      const url = this.generateInvitationUrl(token, baseUrl);
      const canOpen = await Linking.canOpenURL(url);

      if (canOpen) {
        await Linking.openURL(url);
      } else {
        throw new Error('Cannot open URL');
      }
    } catch (error) {
      console.error('Failed to open invitation URL:', error);
      throw error;
    }
  }

  /**
   * Parse URL parameters from invitation URL using expo-linking
   * @param url The URL to parse
   * @returns Parsed URL parameters
   */
  static parseUrl(url: string) {
    return Linking.parse(url);
  }
}

/**
 * Custom hook for handling deep linking in functional components
 * Updated for expo-router patterns
 */
export const useDeepLinking = () => {
  return {
    parseInvitationToken: (url: string) =>
      DeepLinkingService.parseInvitationToken(url),
    isValidInvitationUrl: (url: string) =>
      DeepLinkingService.isValidInvitationUrl(url),
    handleIncomingUrl: (url: string) =>
      DeepLinkingService.handleIncomingUrl(url),
    setupListeners: () => DeepLinkingService.setupDeepLinkListeners(),
    parseUrl: (url: string) => DeepLinkingService.parseUrl(url),
  };
};
