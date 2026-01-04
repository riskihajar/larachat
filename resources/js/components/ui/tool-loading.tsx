import React from 'react';
import { Clock } from 'lucide-react';

interface ToolLoadingProps {
  toolName?: string;
  message?: string;
  className?: string;
}

export function ToolLoading({ 
  toolName = "memproses", 
  message = "Mengambil informasi...", 
  className = "" 
}: ToolLoadingProps) {
  return (
    <div className={`flex items-center gap-2 text-sm text-muted-foreground ${className}`}>
      <div className="relative">
        <Clock className="h-4 w-4 animate-spin" />
        <div className="absolute inset-0 h-4 w-4 rounded-full border-2 border-current border-t-transparent animate-pulse" />
      </div>
      <span className="flex items-center gap-1">
        🔧 <span>{message}</span>
        {toolName && <span className="font-medium">({toolName})</span>}
      </span>
    </div>
  );
}

export function ToolExecutionIndicator({ 
  toolName, 
  arguments: toolArgs, 
  status = "executing" 
}: {
  toolName: string;
  arguments: Record<string, any>;
  status?: "executing" | "completed" | "error";
}) {
  const getStatusIcon = () => {
    switch (status) {
      case "executing":
        return "⚙️";
      case "completed":
        return "✅";
      case "error":
        return "❌";
      default:
        return "🔧";
    }
  };

  const getStatusColor = () => {
    switch (status) {
      case "executing":
        return "text-blue-600";
      case "completed":
        return "text-green-600";
      case "error":
        return "text-red-600";
      default:
        return "text-gray-600";
    }
  };

  return (
    <div className="flex items-start gap-2 p-2 bg-gray-50 rounded-lg border">
      <span className="text-lg">{getStatusIcon()}</span>
      <div className="flex-1 min-w-0">
        <div className={`text-sm font-medium ${getStatusColor()}`}>
          Menjalankan tool: {toolName}
        </div>
        {Object.keys(toolArgs).length > 0 && (
          <div className="text-xs text-gray-500 mt-1">
            Parameter: {JSON.stringify(toolArgs, null, 2)}
          </div>
        )}
      </div>
    </div>
  );
}