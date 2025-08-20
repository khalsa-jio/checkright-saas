import React, { useEffect } from 'react';

import { DeepLinkingService } from '../utils/deepLinking';

interface DeepLinkingProviderProps {
  children: React.ReactNode;
}

/**
 * Provider component that sets up deep linking listeners
 * for invitation URLs and other auth-related links
 */
export function DeepLinkingProvider({ children }: DeepLinkingProviderProps) {
  useEffect(() => {
    // Set up deep linking listeners
    const cleanup = DeepLinkingService.setupDeepLinkListeners();

    // Cleanup listeners on unmount
    return cleanup;
  }, []);

  return <>{children}</>;
}
